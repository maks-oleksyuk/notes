<?php

declare(strict_types=1);

namespace Tests\Fixtures\Imports;

use App\Imports\Filament\SpreadsheetImporter;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable importer double: a test sets the static columns/options before
 * building the action and reads back the constructor arguments it was handed.
 * Call reset() at the start of every test to clear leaked state.
 */
final class StubImporter extends SpreadsheetImporter
{
    /** @var ImportColumn[] */
    public static array $columns = [];

    /** @var array<int, mixed> */
    public static array $optionComponents = [];

    /** @var array<string, string> */
    public static array $capturedMapping;

    /** @var array<string, mixed> */
    public static array $capturedOptions;

    public function __construct(array $mapping = [], array $options = [], ?int $importId = null, ?string $authGuard = null)
    {
        self::$capturedMapping = $mapping;
        self::$capturedOptions = $options;

        parent::__construct($mapping, $options, $importId, $authGuard);
    }

    public static function reset(): void
    {
        self::$columns = [ImportColumn::make('name')];
        self::$optionComponents = [];
        self::$capturedMapping = [];
        self::$capturedOptions = [];
    }

    /**
     * @return ImportColumn[]
     */
    public static function getColumns(): array
    {
        return self::$columns;
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public static function getOptionsFormComponents(): array
    {
        return self::$optionComponents;
    }

    public function resolveRecord(array $row): ?Model
    {
        return null;
    }
}
