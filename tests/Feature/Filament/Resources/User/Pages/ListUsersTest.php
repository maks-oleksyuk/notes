<?php

declare(strict_types=1);

use App\Filament\Exports\UserExporter;
use App\Filament\Resources\User\Pages\ListUsers;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Support\Colors\Color;

covers(ListUsers::class);

describe('Filament | User List', function (): void {
    it('renders export actions with correct configuration', function (): void {
        $actions = new ReflectionMethod(
            ListUsers::class,
            'getHeaderActions',
        )->invoke(new ListUsers);

        expect($actions)->toHaveCount(2);

        /** @var ExportAction $csvAction */
        $csvAction = $actions[0];
        expect($csvAction->getName())->toBe('export_csv')
            ->and($csvAction->getLabel())->toBe('Export CSV')
            ->and($csvAction->getExporter())->toBe(UserExporter::class)
            ->and($csvAction->getIcon())->toEqual('heroicon-m-document-arrow-down')
            ->and($csvAction->getFormats())->toEqual([ExportFormat::Csv])
            ->and($csvAction->hasColumnMapping())->toBeFalse()
            ->and($csvAction->getColor())->toBe(Color::Sky)
            ->and($csvAction->shouldOpenModal())->toBeFalse()
            ->and($csvAction->isOutlined())->toBeTrue();

        /** @var ExportAction $xlsAction */
        $xlsAction = $actions[1];
        expect($xlsAction->getName())->toBe('export_xls')
            ->and($xlsAction->getLabel())->toBe('Export XLSX')
            ->and($xlsAction->getExporter())->toBe(UserExporter::class)
            ->and($xlsAction->getIcon())->toEqual('heroicon-m-document-arrow-down')
            ->and($xlsAction->getFormats())->toEqual([ExportFormat::Xlsx])
            ->and($xlsAction->hasColumnMapping())->toBeFalse()
            ->and($xlsAction->getColor())->toBe(Color::Green)
            ->and($xlsAction->shouldOpenModal())->toBeFalse()
            ->and($xlsAction->isOutlined())->toBeTrue();
    });
});
