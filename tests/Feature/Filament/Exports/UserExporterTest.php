<?php

declare(strict_types=1);

use App\Filament\Exports\UserExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Pest\Expectation;

covers(UserExporter::class);

describe('Filament | Export | User', function (): void {
    it('returns the correct model',
        fn (): Expectation => expect(UserExporter::getModel())->toBe(User::class)
    );

    it('returns the correct columns', function (): void {
        $columns = UserExporter::getColumns();

        expect($columns)
            ->toHaveCount(2)
            ->and($columns[0]->getName())->toBe('name')
            ->and($columns[1]->getName())->toBe('email');
    });

    it('generates a correct success notification without failed rows', function (): void {
        $user = User::factory()->create();

        $export = new Export;
        $export->user()->associate($user);
        $export->fill([
            'successful_rows' => 5,
            'total_rows' => 5,
            'file_disk' => 'local',
            'file_name' => 'export.csv',
            'exporter' => UserExporter::class,
        ])->save();

        $message = UserExporter::getCompletedNotificationBody($export);

        expect($message)->toBe('Your user export has completed and 5 rows exported.');
    });

    it('generates a correct success notification with failed rows', function (): void {
        $user = User::factory()->create();

        $export = new Export;
        $export->user()->associate($user);
        $export->fill([
            'successful_rows' => 5,
            'total_rows' => 7,
            'file_disk' => 'local',
            'file_name' => 'export.csv',
            'exporter' => UserExporter::class,
        ])->save();

        $message = UserExporter::getCompletedNotificationBody($export);

        expect($message)->toBe('Your user export has completed and 5 rows exported. 2 rows failed to export.');
    });
});
