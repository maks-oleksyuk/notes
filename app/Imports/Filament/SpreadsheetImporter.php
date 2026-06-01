<?php

declare(strict_types=1);

namespace App\Imports\Filament;

use App\Exports\ImporterTemplateExport;
use Filament\Actions\Imports\ImportColumn;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Base maatwebsite/excel importer that mirrors Filament's Importer DX.
 *
 * A concrete importer only declares its columns and how a row resolves to a
 * model. The same class is reusable from a Filament action or a plain API
 * request: instantiate with a heading mapping and hand it to the Excel facade.
 */
abstract class SpreadsheetImporter implements ToModel, WithChunkReading, WithHeadingRow
{
    use Importable;

    protected int $importedRows = 0;

    protected int $skippedRows = 0;

    /** @var array<int, array{row: array<string, mixed>, errors: array<int, string>}> */
    protected array $failedRows = [];

    /**
     * @param  array<string, string>  $mapping  columnName => spreadsheet heading
     * @param  array<string, mixed>  $options  values from the modal option toggles
     */
    public function __construct(
        protected array $mapping = [],
        protected array $options = [],
    ) {}

    /**
     * @return array<int, ImportColumn>
     */
    abstract public static function getColumns(): array;

    /**
     * Turn a mapped, validated row into a persistable model.
     * Return null to deliberately skip the row (counted as skipped, not failed).
     *
     * @param  array<string, string|null>  $row
     */
    abstract public function resolveRecord(array $row): ?Model;

    /**
     * Filament form components rendered in the import modal as user options.
     * Their state is passed back to the importer as the $options array.
     *
     * @return array<int, Component>
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
            $this->failedRows[] = [
                'row' => $mapped,
                'errors' => array_values($validator->errors()->all()),
            ];

            return null;
        }

        $record = $this->resolveRecord($mapped);

        if (! $record instanceof Model) {
            $this->skippedRows++;

            return null;
        }

        $this->importedRows++;

        return $record;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }

    public function getFailedRowsCount(): int
    {
        return count($this->failedRows);
    }

    /**
     * @return array<int, array{row: array<string, mixed>, errors: array<int, string>}>
     */
    public function getFailedRows(): array
    {
        return $this->failedRows;
    }

    public static function getCompletedNotificationBody(self $importer): string
    {
        $imported = $importer->getImportedRows();
        $body = sprintf('Imported %d ', $imported).str('row')->plural($imported).'.';

        if (($skipped = $importer->getSkippedRows()) !== 0) {
            $body .= sprintf(' %s ', $skipped).str('row')->plural($skipped).' skipped.';
        }

        if (($failed = $importer->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s ', $failed).str('row')->plural($failed).' failed to import.';
        }

        return $body;
    }

    /**
     * Template (heading row + one example row) generated from the columns.
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

        foreach (static::getColumns() as $column) {
            $heading = (string) ($this->mapping[$column->getName()] ?? $column->getName());
            $value = $row[$heading] ?? null;
            $mapped[$column->getName()] = is_scalar($value) ? (string) $value : null;
        }

        return $mapped;
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function validationRules(): array
    {
        $rules = [];

        foreach (static::getColumns() as $column) {
            $columnRules = $column->getDataValidationRules();
            if ($columnRules !== []) {
                $rules[$column->getName()] = $columnRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        return [];
    }
}
