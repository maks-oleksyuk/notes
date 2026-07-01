<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Generic example/template export driven by an importer's column definitions.
 * Used to offer downloadable CSV/XLSX samples that always match the importer.
 */
final readonly class ImporterTemplateExport implements FromArray, WithHeadings
{
    /**
     * @param  string[]  $headings
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(
        private array $headings,
        private array $rows,
    ) {}

    /**
     * @return string[]
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return $this->rows;
    }
}
