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
                ->label('Export CSV')
                ->exporter(UserExporter::class)
                ->icon('heroicon-m-document-arrow-down')
                ->formats([ExportFormat::Csv])
                ->columnMapping(false)
                ->color(Color::Sky)
                ->modal(false)
                ->outlined(),

            Actions\ExportAction::make('export_xls')
                ->label('Export XLSX')
                ->exporter(UserExporter::class)
                ->icon('heroicon-m-document-arrow-down')
                ->formats([ExportFormat::Xlsx])
                ->columnMapping(false)
                ->color(Color::Green)
                ->modal(false)
                ->outlined(),
        ];
    }
}
