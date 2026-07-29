<?php

declare(strict_types=1);

use App\Filament\Exports\UserExporter;
use App\Filament\Resources\User\Pages\ListUsers;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

use function Pest\Livewire\livewire;

covers(ListUsers::class);

describe('Filament | Resource | User | List', function (): void {
    it('renders export actions with correct configuration', function (): void {
        livewire(ListUsers::class)
            ->assertActionExists('export_csv', function (ExportAction $action): bool {
                expect($action->getLabel())->toBe('Export CSV')
                    ->and($action->getExporter())->toBe(UserExporter::class)
                    ->and($action->getIcon())->toEqual(Heroicon::OutlinedDocumentArrowDown)
                    ->and($action->getFormats())->toEqual([ExportFormat::Csv])
                    ->and($action->hasColumnMapping())->toBeFalse()
                    ->and($action->getColor())->toBe(Color::Sky)
                    ->and($action->shouldOpenModal())->toBeFalse()
                    ->and($action->isOutlined())->toBeTrue();

                return true;
            })
            ->assertActionExists('export_xls', function (ExportAction $action): bool {
                expect($action->getLabel())->toBe('Export XLSX')
                    ->and($action->getExporter())->toBe(UserExporter::class)
                    ->and($action->getIcon())->toEqual(Heroicon::OutlinedDocumentArrowDown)
                    ->and($action->getFormats())->toEqual([ExportFormat::Xlsx])
                    ->and($action->hasColumnMapping())->toBeFalse()
                    ->and($action->getColor())->toBe(Color::Green)
                    ->and($action->shouldOpenModal())->toBeFalse()
                    ->and($action->isOutlined())->toBeTrue();

                return true;
            });
    });
});
