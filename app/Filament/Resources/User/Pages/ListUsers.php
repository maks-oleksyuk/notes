<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\Pages;

use App\Filament\Exports\UserExporter;
use App\Filament\Resources\User\UserResource;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

final class ListUsers extends ListRecords
{
    #[\Override]
    protected static string $resource = UserResource::class;

    /**
     * @return ExportAction[]
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export_csv')
                ->label('Export CSV')
                ->exporter(UserExporter::class)
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->formats([ExportFormat::Csv])
                ->columnMapping(false)
                ->color(Color::Sky)
                ->modal(false)
                ->outlined(),

            ExportAction::make('export_xls')
                ->label('Export XLSX')
                ->exporter(UserExporter::class)
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->formats([ExportFormat::Xlsx])
                ->columnMapping(false)
                ->color(Color::Green)
                ->modal(false)
                ->outlined(),
        ];
    }
}
