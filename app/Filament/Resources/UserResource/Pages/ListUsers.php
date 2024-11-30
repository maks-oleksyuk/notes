<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Exports\UserExporter;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make('export_csv')
                ->exporter(UserExporter::class)
                ->columnMapping(false)
                ->formats([ExportFormat::Csv])
                ->color(Color::Sky)
                ->modal(false)
                ->outlined()
                ->icon('heroicon-m-document-arrow-down')
                ->label('Export CSV'),

            Actions\ExportAction::make('export_xls')
                ->exporter(UserExporter::class)
                ->columnMapping(false)
                ->formats([ExportFormat::Xlsx])
                ->color(Color::Green)
                ->modal(false)
                ->outlined()
                ->icon('heroicon-m-document-arrow-down')
                ->label('Export XLSX'),
        ];
    }
}
