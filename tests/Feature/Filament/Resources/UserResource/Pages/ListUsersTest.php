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

describe('Filament | User List - additional', function (): void {
    it('returns two ExportAction instances in expected order with unique names', function (): void {
        $actions = (new ReflectionMethod(
            ListUsers::class,
            'getHeaderActions',
        ))->invoke(new ListUsers);

        expect(is_array($actions))->toBeTrue()
            ->and(array_is_list($actions))->toBeTrue()
            ->and($actions)->toHaveCount(2);

        foreach ($actions as $action) {
            expect($action)->toBeInstanceOf(ExportAction::class);
        }

        $names = array_map(
            fn (ExportAction $a) => $a->getName(),
            $actions,
        );

        // Order and uniqueness
        expect($names)->toEqual(['export_csv', 'export_xls'])
            ->and(array_unique($names))->toHaveCount(2);
    });

    it('ensures exporters and icons are consistent across both actions', function (): void {
        /** @var array<int, ExportAction> $actions */
        $actions = (new ReflectionMethod(
            ListUsers::class,
            'getHeaderActions',
        ))->invoke(new ListUsers);

        [$csvAction, $xlsAction] = $actions;

        expect($csvAction->getExporter())->toBe(UserExporter::class)
            ->and($xlsAction->getExporter())->toBe(UserExporter::class)
            ->and($csvAction->getIcon())->toEqual('heroicon-m-document-arrow-down')
            ->and($xlsAction->getIcon())->toEqual('heroicon-m-document-arrow-down');
    });

    it('ensures formats are specific per action (CSV only for CSV action, XLSX only for XLS action)', function (): void {
        /** @var array<int, ExportAction> $actions */
        $actions = (new ReflectionMethod(
            ListUsers::class,
            'getHeaderActions',
        ))->invoke(new ListUsers);

        [$csvAction, $xlsAction] = $actions;

        expect($csvAction->getFormats())->toEqual([ExportFormat::Csv])
            ->and($xlsAction->getFormats())->toEqual([ExportFormat::Xlsx]);
    });

    it('ensures actions are non-modal, outlined, and have no column mapping', function (): void {
        /** @var array<int, ExportAction> $actions */
        $actions = (new ReflectionMethod(
            ListUsers::class,
            'getHeaderActions',
        ))->invoke(new ListUsers);

        foreach ($actions as $action) {
            expect($action->shouldOpenModal())->toBeFalse()
                ->and($action->isOutlined())->toBeTrue()
                ->and($action->hasColumnMapping())->toBeFalse();
        }
    });

    it('returns new action instances on subsequent calls to prevent shared state', function (): void {
        $ref = new ReflectionMethod(ListUsers::class, 'getHeaderActions');

        /** @var array<int, ExportAction> $first */
        $first = $ref->invoke(new ListUsers);

        /** @var array<int, ExportAction> $second */
        $second = $ref->invoke(new ListUsers);

        // Ensure we get fresh objects each time (no accidental static caching)
        expect(spl_object_id($first[0]))->not->toBe(spl_object_id($second[0]))
            ->and(spl_object_id($first[1]))->not->toBe(spl_object_id($second[1]));
    });
});
