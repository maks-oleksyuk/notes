<?php

declare(strict_types=1);

use App\Imports\Filament\FinalizeSpreadsheetImportListener;
use App\Imports\Filament\SpreadsheetImporter;
use App\Imports\UserImporter;
use App\Models\User;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

covers(FinalizeSpreadsheetImportListener::class, SpreadsheetImporter::class);

function makeImport(User $user, int $processedRows = 0, int $successfulRows = 0): Import
{
    $import = new Import;
    $import->forceFill([
        'file_name' => 'users.xlsx',
        'file_path' => 'imports/users.xlsx',
        'importer' => UserImporter::class,
        'user_id' => $user->getKey(),
        'total_rows' => 0,
        'processed_rows' => $processedRows,
        'successful_rows' => $successfulRows,
    ])->save();

    return $import;
}

function makeFailedRow(Import $import): FailedImportRow
{
    $failedRow = new FailedImportRow;
    $failedRow->forceFill([
        'import_id' => $import->getKey(),
        'data' => ['name' => 'Bad', 'email' => 'broken'],
        'validation_error' => 'The email field must be a valid email address.',
    ])->save();

    return $failedRow;
}

describe('Import | Filament | FinalizeSpreadsheetImportListener', function (): void {
    it('does nothing for a stateless importer', function (): void {
        Storage::fake('local');

        $listener = App::make(FinalizeSpreadsheetImportListener::class);

        $listener->handle(new UserImporter);

        $this->assertDatabaseCount(Import::class, 0);
    });

    it('does not query the database when importId is null', function (): void {
        DB::connection()->enableQueryLog();

        App::make(FinalizeSpreadsheetImportListener::class)->handle(new UserImporter);

        expect(DB::connection()->getQueryLog())->toBeEmpty();

        DB::connection()->disableQueryLog();
    });

    it('finalizes a fully successful import with a success notification', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('imports/users.xlsx', 'content');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 5, successfulRows: 5);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey()));

        $import->refresh();

        expect($import->completed_at)->not->toBeNull()
            ->and($import->total_rows)->toBe(5)
            ->and(Storage::disk('local')->exists('imports/users.xlsx'))->toBeFalse();

        $notification = $user->notifications()->sole();

        expect($notification->data['status'])->toBe('success')
            ->and($notification->data['body'])->toBe('Imported 5 rows.')
            ->and($notification->data['actions'])->toBeEmpty();
    });

    it('sends a warning notification with a download action when some rows failed', function (): void {
        Storage::fake('local');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 3, successfulRows: 2);
        makeFailedRow($import);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey(), authGuard: 'web'));

        $notification = $user->notifications()->sole();

        $action = $notification->data['actions'][0];

        expect($notification->data['status'])->toBe('warning')
            ->and($notification->data['body'])->toBe('Imported 2 rows. 1 row failed to import.')
            ->and($notification->data['actions'])->toHaveCount(1)
            ->and($action['url'])->toContain('failed-rows')
            ->and($action['url'])->toContain('web')
            ->and($action['url'])->toContain('signature=')
            // absolute: false → the signed URL is relative, not a full http(s) URL.
            ->and($action['url'])->not->toStartWith('http')
            ->and($action['shouldOpenUrlInNewTab'])->toBeTrue();
    });

    it('sends a danger notification when every row failed', function (): void {
        Storage::fake('local');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 2, successfulRows: 0);
        makeFailedRow($import);
        makeFailedRow($import);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey()));

        expect($user->notifications()->sole()->data['status'])->toBe('danger');
    });

    it('counts skipped rows in the notification body', function (): void {
        Storage::fake('local');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 5, successfulRows: 3);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey()));

        expect($user->notifications()->sole()->data['body'])
            ->toBe('Imported 3 rows. 2 rows skipped.');
    });

    it('includes exactly one skipped row in the notification body', function (): void {
        Storage::fake('local');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 2, successfulRows: 1);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey()));

        expect($user->notifications()->sole()->data['body'])
            ->toBe('Imported 1 row. 1 row skipped.');
    });

    it('does not finalize the same import twice', function (): void {
        Storage::fake('local');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 1, successfulRows: 1);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey()));
        $listener->handle(new UserImporter(importId: $import->getKey()));

        expect($user->notifications()->count())->toBe(1);
    });

    it('closes a failed import and notifies the owner with danger status', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('imports/users.xlsx', 'content');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 4, successfulRows: 4);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handleFailure(new UserImporter(importId: $import->getKey()));

        $import->refresh();

        expect($import->completed_at)->not->toBeNull()
            ->and($import->total_rows)->toBe(4)
            ->and(Storage::disk('local')->exists('imports/users.xlsx'))->toBeFalse();

        $notification = $user->notifications()->sole();

        expect($notification->data['status'])->toBe('danger')
            ->and($notification->data['body'])->toContain('4 rows were processed');
    });

    it('does not report a failure for an already finalized import', function (): void {
        Storage::fake('local');

        $user = User::factory()->create();
        $import = makeImport($user, processedRows: 1, successfulRows: 1);

        $listener = App::make(FinalizeSpreadsheetImportListener::class);
        $listener->handle(new UserImporter(importId: $import->getKey()));
        $listener->handleFailure(new UserImporter(importId: $import->getKey()));

        expect($user->notifications()->count())->toBe(1)
            ->and($user->notifications()->sole()->data['status'])->toBe('success');
    });

    it('ignores a failure for a stateless importer', function (): void {
        $listener = App::make(FinalizeSpreadsheetImportListener::class);

        $listener->handleFailure(new UserImporter);

        $this->assertDatabaseCount(Import::class, 0);
    });
});
