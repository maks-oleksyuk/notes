<?php

declare(strict_types=1);

namespace App\Imports\Filament;

use App\Exports\ImporterTemplateExport;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

/**
 * Base maatwebsite/excel importer that mirrors Filament's Importer DX.
 *
 * A concrete importer only declares its columns and how a row resolves to a
 * model. The same class is reusable from a Filament action or a plain API
 * request: instantiate with a heading mapping and hand it to the Excel facade.
 *
 * Queued mode: concrete importer implements ShouldQueue. Pass $importId from
 * SpreadsheetImportAction to enable DB-backed progress tracking and async
 * notifications via FinalizeSpreadsheetImportListener.
 */
abstract class SpreadsheetImporter implements ToModel, WithChunkReading, WithEvents, WithHeadingRow
{
    use Importable;

    public protected(set) int $importedRows = 0;

    public protected(set) int $skippedRows = 0;

    /** @var array<int, array{row: array<string, mixed>, errors: string[]}> */
    public protected(set) array $failedRows = [];

    /**
     * Per-instance caches and per-chunk progress buffers. Never serialized:
     * the queue chain is built (and the importer serialized) before any row
     * runs, so these stay null/zero in the payload and are repopulated lazily
     * inside each chunk job.
     *
     * @var ImportColumn[]|null
     */
    private ?array $cachedColumns = null;

    /** @var array<string, array<int|string, mixed>>|null */
    private ?array $cachedValidationRules = null;

    private int $pendingProcessedRows = 0;

    private int $pendingSuccessfulRows = 0;

    /**
     * @param  array<string, string>  $mapping  columnName => spreadsheet heading
     * @param  array<string, mixed>  $options  values from the modal option toggles
     * @param  ?string  $authGuard  panel guard captured at request time, used to
     *                              sign the failed-rows download URL from the queue
     */
    public function __construct(
        protected array $mapping = [],
        protected array $options = [],
        protected(set) ?int $importId = null,
        protected(set) ?string $authGuard = null,
    ) {}

    /**
     * @return ImportColumn[]
     */
    abstract public static function getColumns(): array;

    /**
     * Turn a mapped, validated row into a persistable model.
     * Return null to deliberately skip the row (counted as skipped, not failed).
     *
     * @param  array<string, ?string>  $row
     */
    abstract public function resolveRecord(array $row): ?Model;

    /**
     * Filament form components rendered in the import modal as user options.
     * Their state is passed back to the importer as the $options array.
     *
     * @return Component[]
     */
    public static function getOptionsFormComponents(): array
    {
        return [];
    }

    public static function getModalDescription(): ?string
    {
        return null;
    }

    public static function getFileUploadHint(): ?string
    {
        return null;
    }

    /**
     * @return array<class-string, \Closure(): void>
     *
     * @throws BindingResolutionException
     */
    public function registerEvents(): array
    {
        $listener = App::make(FinalizeSpreadsheetImportListener::class);

        return [
            AfterChunk::class => $this->flushProgress(...),
            AfterImport::class => function () use ($listener): void {
                $this->flushProgress();
                $listener->handle($this);
            },
            // No flushProgress() here: the failed chunk's transaction is
            // rolled back, so buffered counts refer to rows that no longer exist.
            ImportFailed::class => fn () => $listener->handleFailure($this),
        ];
    }

    /**
     * Read a single import option value (shared by Filament and API callers).
     */
    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $row  raw heading-keyed row
     */
    public function model(array $row): ?Model
    {
        $mapped = $this->mapRow($row);

        $validator = Validator::make($mapped, $this->validationRules(), $this->validationMessages());

        if ($validator->fails()) {
            $this->recordFailure($mapped, array_values($validator->errors()->all()));

            return null;
        }

        try {
            $record = $this->resolveRecord($mapped);

            if (! $record instanceof Model) {
                $this->recordSkip();

                return null;
            }

            $record->save();
        } catch (UniqueConstraintViolationException) {
            // A data problem (duplicate in the file or an existing record),
            // not a bug — record a readable error instead of the raw SQL.
            $this->recordFailure($mapped, [__('filament/import.errors.duplicate')]);

            return null;
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->recordFailure($mapped, [$throwable->getMessage()]);

            return null;
        }

        $this->recordSuccess();

        // The record is persisted above, so an exception on a single row never aborts the whole chunk.
        // Returning null stops maatwebsite from saving the same model a second time.
        return null;
    }

    private function recordSuccess(): void
    {
        if ($this->importId !== null) {
            $this->pendingProcessedRows++;
            $this->pendingSuccessfulRows++;
        } else {
            $this->importedRows++;
        }
    }

    private function recordSkip(): void
    {
        if ($this->importId !== null) {
            $this->pendingProcessedRows++;
        } else {
            $this->skippedRows++;
        }
    }

    /**
     * @param  array<string, ?string>  $mapped
     * @param  string[]  $errors
     */
    private function recordFailure(array $mapped, array $errors): void
    {
        if ($this->importId !== null) {
            $failedRow = new FailedImportRow;
            $failedRow->forceFill([
                'import_id' => $this->importId,
                'data' => $mapped,
                'validation_error' => implode(' | ', $errors),
            ])->save();
            $this->pendingProcessedRows++;
        } else {
            $this->failedRows[] = [
                'row' => $mapped,
                'errors' => $errors,
            ];
        }
    }

    /**
     * Persist buffered row counters in one query instead of one per row.
     * Called after each chunk (AfterChunk runs inside the chunk transaction)
     * and again on AfterImport/ImportFailed as a safety net.
     */
    private function flushProgress(): void
    {
        if ($this->importId === null || $this->pendingProcessedRows === 0) {
            return;
        }

        Import::query()->where('id', $this->importId)->incrementEach([
            'processed_rows' => $this->pendingProcessedRows,
            'successful_rows' => $this->pendingSuccessfulRows,
        ]);

        $this->pendingProcessedRows = 0;
        $this->pendingSuccessfulRows = 0;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public static function getCompletedNotificationBody(Import $import, int $failedCount): string
    {
        $imported = $import->successful_rows ?? 0; // @pest-mutate-ignore: column is NOT NULL, the default never applies
        $body = sprintf('Imported %d ', $imported).str('row')->plural($imported).'.';

        $skipped = max(0, ($import->processed_rows ?? 0) - $imported - $failedCount);

        if ($skipped > 0) {
            $body .= sprintf(' %s ', $skipped).str('row')->plural($skipped).' skipped.';
        }

        if ($failedCount > 0) {
            $body .= sprintf(' %s ', $failedCount).str('row')->plural($failedCount).' failed to import.';
        }

        return $body;
    }

    /**
     * Template (heading row and one example row) generated from the columns.
     */
    public static function makeTemplateExport(): ImporterTemplateExport
    {
        $headings = [];
        $example = [];

        foreach (static::getColumns() as $column) {
            $headings[] = $column->getName();
            $first = Arr::first($column->getExamples());
            $example[] = is_scalar($first) ? (string) $first : '';
        }

        return new ImporterTemplateExport($headings, [$example]);
    }

    /**
     * Remap a raw spreadsheet row to column names using the configured mapping.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    protected function mapRow(array $row): array
    {
        $mapped = [];

        foreach ($this->columns() as $column) {
            $heading = $this->mapping[$column->getName()] ?? $column->getName();
            $value = $row[$heading] ?? null;
            // Trim stray spreadsheet whitespace so required rules reject blank
            // cells and validators do not receive padded values.
            $mapped[$column->getName()] = is_scalar($value) ? mb_trim((string) $value) : null;
        }

        return $mapped;
    }

    /**
     * @return array<string, array<int|string, mixed>>
     */
    protected function validationRules(): array
    {
        if ($this->cachedValidationRules !== null) {
            return $this->cachedValidationRules; // @pest-mutate-ignore: cache hit/miss is behaviorally identical
        }

        $rules = [];

        foreach ($this->columns() as $column) {
            $columnRules = $column->getDataValidationRules();
            if ($columnRules !== []) {
                $rules[$column->getName()] = $columnRules;
            }
        }

        return $this->cachedValidationRules = $rules;
    }

    /**
     * getColumns() rebuilds the whole ImportColumn graph on every call, and
     * model() needs it twice per row — cache it for the lifetime of the
     * instance (one chunk job in queued mode).
     *
     * @return ImportColumn[]
     */
    private function columns(): array
    {
        return $this->cachedColumns ??= static::getColumns();
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        return [];
    }
}
