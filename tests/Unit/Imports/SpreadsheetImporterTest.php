<?php

declare(strict_types=1);

use App\Imports\Filament\SpreadsheetImporter;
use App\Imports\UserImporter;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

covers(SpreadsheetImporter::class);

describe('Import | Filament | SpreadsheetImporter', function (): void {
    it('isolates an exception thrown by resolveRecord as a failed row', function (): void {
        Exceptions::fake();

        $importer = new class extends SpreadsheetImporter
        {
            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                return [
                    ImportColumn::make('name')->rules(['required']),
                ];
            }

            /**
             * @param  array<string, string|null>  $row
             */
            public function resolveRecord(array $row): ?Model
            {
                throw new RuntimeException('boom');
            }
        };

        $importer->model(['name' => 'Anything']);

        expect($importer->failedRows)->toHaveCount(1)
            ->and($importer->failedRows[0]['errors'])->toBe(['boom'])
            ->and($importer->importedRows)->toBe(0);

        Exceptions::assertReported(RuntimeException::class);
    });

    it('calls getColumns() only once regardless of how many rows are processed', function (): void {
        $importer = new class extends SpreadsheetImporter
        {
            public static int $calls = 0;

            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                self::$calls++;

                return [ImportColumn::make('name')->rules(['required'])];
            }

            public function resolveRecord(array $row): ?Model
            {
                return null;
            }
        };
        $importer::$calls = 0;

        $importer->model(['name' => 'First']);
        $importer->model(['name' => 'Second']);

        expect($importer::$calls)->toBe(1);
    });

    it('computes validation rules only once regardless of how many rows are processed', function (): void {
        $evaluations = 0;

        $importer = new class extends SpreadsheetImporter
        {
            public static ?Closure $spy = null;

            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                return [ImportColumn::make('name')->rules(self::$spy)];
            }

            public function resolveRecord(array $row): ?Model
            {
                return null;
            }
        };

        $importer::$spy = function () use (&$evaluations): array {
            $evaluations++;

            return ['required'];
        };

        $importer->model(['name' => 'First']);
        $importer->model(['name' => 'Second']);

        expect($evaluations)->toBe(1);
    });

    it('records a readable error instead of raw SQL on a unique constraint violation', function (): void {
        $importer = new class extends SpreadsheetImporter
        {
            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                return [
                    ImportColumn::make('name')->rules(['required']),
                ];
            }

            /**
             * @param  array<string, string|null>  $row
             */
            public function resolveRecord(array $row): ?Model
            {
                throw new UniqueConstraintViolationException('pgsql', 'insert into users', [], new Exception('duplicate key'));
            }
        };

        $importer->model(['name' => 'Dup']);

        expect($importer->failedRows)->toHaveCount(1)
            ->and($importer->failedRows[0]['errors'][0])->toBe('Duplicate value: a record with the same unique field already exists.')
            ->and($importer->failedRows[0]['errors'][0])->not->toContain('insert into');
    });

    it('builds a template export from the column definitions', function (): void {
        $export = UserImporter::makeTemplateExport();

        expect($export->headings())->toBe(['name', 'email'])
            ->and($export->array())->toBe([['John Doe', 'john@example.com']]);
    });

    it('buffers DB progress counters and flushes them once per chunk', function (): void {
        $owner = User::factory()->create();

        $import = new Import;
        $import->forceFill([
            'file_name' => 'users.xlsx',
            'file_path' => 'imports/users.xlsx',
            'importer' => UserImporter::class,
            'user_id' => $owner->getKey(),
            'total_rows' => 0,
        ])->save();

        $importer = new UserImporter(importId: $import->getKey());

        $importer->model(['name' => 'Valid', 'email' => 'valid@example.com']);
        $importer->model(['name' => 'Bad', 'email' => 'not-an-email']);

        expect($import->refresh()->processed_rows)->toBe(0);

        ($importer->registerEvents()[AfterChunk::class])();

        $import->refresh();

        expect($import->processed_rows)->toBe(2)
            ->and($import->successful_rows)->toBe(1)
            ->and($import->failedRows()->count())->toBe(1)
            ->and($import->failedRows()->first()->validation_error)->not->toBeNull();

        $this->assertDatabaseHas(User::class, ['email' => 'valid@example.com']);
    });

    it('flushes a single pending row immediately', function (): void {
        $owner = User::factory()->create();

        $import = new Import;
        $import->forceFill([
            'file_name' => 'users.xlsx',
            'file_path' => 'imports/users.xlsx',
            'importer' => UserImporter::class,
            'user_id' => $owner->getKey(),
            'total_rows' => 0,
        ])->save();

        $importer = new UserImporter(importId: $import->getKey());
        $flush = $importer->registerEvents()[AfterChunk::class];

        $importer->model(['name' => 'Alice', 'email' => 'alice@example.com']);
        $flush();

        expect($import->refresh()->processed_rows)->toBe(1)
            ->and($import->refresh()->successful_rows)->toBe(1);
    });

    it('resets progress counters to zero after flushing so a second flush is a no-op', function (): void {
        $owner = User::factory()->create();

        $import = new Import;
        $import->forceFill([
            'file_name' => 'users.xlsx',
            'file_path' => 'imports/users.xlsx',
            'importer' => UserImporter::class,
            'user_id' => $owner->getKey(),
            'total_rows' => 0,
        ])->save();

        $importer = new UserImporter(importId: $import->getKey());
        $flush = $importer->registerEvents()[AfterChunk::class];

        $importer->model(['name' => 'Alice', 'email' => 'alice@example.com']);
        $flush();
        $flush();

        expect($import->refresh()->processed_rows)->toBe(1)
            ->and($import->refresh()->successful_rows)->toBe(1);
    });

    it('skips the flush when no rows are buffered', function (): void {
        $owner = User::factory()->create();

        $import = new Import;
        $import->forceFill([
            'file_name' => 'users.xlsx',
            'file_path' => 'imports/users.xlsx',
            'importer' => UserImporter::class,
            'user_id' => $owner->getKey(),
            'total_rows' => 0,
        ])->save();

        $importer = new UserImporter(importId: $import->getKey());

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        ($importer->registerEvents()[AfterChunk::class])();

        // With nothing buffered, the early guard must skip the increment query entirely.
        $updateQueries = new Collection(DB::connection()->getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'update'));

        expect($updateQueries)->toBeEmpty()
            ->and($import->refresh()->processed_rows)->toBe(0);
    });

    it('registers an ImportFailed handler in its events', function (): void {
        $importer = new UserImporter;

        expect($importer->registerEvents())->toHaveKey(ImportFailed::class);
    });

    it('registers an AfterImport handler in its events', function (): void {
        $importer = new UserImporter;

        expect($importer->registerEvents())->toHaveKey(AfterImport::class);
    });

    it('accumulates successful rows across two chunks so the reset is observable', function (): void {
        $owner = User::factory()->create();

        $import = new Import;
        $import->forceFill([
            'file_name' => 'users.xlsx',
            'file_path' => 'imports/users.xlsx',
            'importer' => UserImporter::class,
            'user_id' => $owner->getKey(),
            'total_rows' => 0,
        ])->save();

        $importer = new UserImporter(importId: $import->getKey());
        $flush = $importer->registerEvents()[AfterChunk::class];

        $importer->model(['name' => 'Alice', 'email' => 'alice@example.com']);
        $flush();

        $importer->model(['name' => 'Bob', 'email' => 'bob@example.com']);
        $flush();

        expect($import->refresh()->processed_rows)->toBe(2)
            ->and($import->refresh()->successful_rows)->toBe(2);
    });

    it('builds a template export replacing non-scalar examples with an empty string', function (): void {
        $importer = new class extends SpreadsheetImporter
        {
            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                return [
                    ImportColumn::make('name')->example('Alice'),
                    // A non-string scalar example proves the (string) cast runs.
                    ImportColumn::make('age')->example(42),
                    ImportColumn::make('code'),
                ];
            }

            /**
             * @param  array<string, string|null>  $row
             */
            public function resolveRecord(array $row): ?Model
            {
                return null;
            }
        };

        $export = $importer::makeTemplateExport();

        expect($export->headings())->toBe(['name', 'age', 'code'])
            ->and($export->array())->toBe([['Alice', '42', '']]);
    });

    it('coerces a non-string scalar cell value to a string when mapping rows', function (): void {
        $importer = new class extends SpreadsheetImporter
        {
            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                return [
                    ImportColumn::make('name')->rules(['required']),
                ];
            }

            /**
             * @param  array<string, string|null>  $row
             */
            public function resolveRecord(array $row): ?Model
            {
                return null;
            }
        };

        $importer->model(['name' => 42]);

        expect($importer->skippedRows)->toBe(1)
            ->and($importer->failedRows)->toBeEmpty();
    });

    it('provides empty defaults for the optional importer hooks', function (): void {
        $importer = new class extends SpreadsheetImporter
        {
            /**
             * @return ImportColumn[]
             */
            public static function getColumns(): array
            {
                return [];
            }

            /**
             * @param  array<string, string|null>  $row
             */
            public function resolveRecord(array $row): ?Model
            {
                return null;
            }
        };

        expect($importer::getOptionsFormComponents())->toBeEmpty()
            ->and($importer::getModalDescription())->toBeNull()
            ->and($importer::getFileUploadHint())->toBeNull()
            ->and($importer->chunkSize())->toBe(1000);
    });

    it('flushes progress and finalizes the import when the AfterImport event fires', function (): void {
        Storage::fake('local');

        $owner = User::factory()->create();
        User::factory()->create(['email' => 'existing@example.com']);

        $import = new Import;
        $import->forceFill([
            'file_name' => 'users.xlsx',
            'file_path' => 'imports/users.xlsx',
            'importer' => UserImporter::class,
            'user_id' => $owner->getKey(),
            'total_rows' => 0,
        ])->save();

        $importer = new UserImporter(options: ['skipExisting' => true], importId: $import->getKey());

        // Existing email + skipExisting → recorded as a skip through the importId branch.
        $importer->model(['name' => 'Whoever', 'email' => 'existing@example.com']);

        ($importer->registerEvents()[AfterImport::class])();

        $import->refresh();

        expect($import->processed_rows)->toBe(1)
            ->and($import->successful_rows)->toBe(0)
            ->and($import->completed_at)->not->toBeNull()
            ->and($owner->notifications()->sole()->data['status'])->toBe('success');
    });
});
