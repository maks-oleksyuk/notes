<?php

declare(strict_types=1);

use App\Filament\Exports\UserExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Expectation;

covers(UserExporter::class);

uses(RefreshDatabase::class);

describe('Filament | User Exporter', function (): void {
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

        $export = Export::query()->create([
            'user_id' => $user->id,
            'successful_rows' => 5,
            'total_rows' => 5,
            'file_disk' => 'local',
            'file_name' => 'export.csv',
            'exporter' => UserExporter::class,
        ]);

        $message = UserExporter::getCompletedNotificationBody($export);

        expect($message)->toBe('Your user export has completed and 5 rows exported.');
    });

    it('generates a correct success notification with failed rows', function (): void {
        $user = User::factory()->create();

        $export = Export::query()->create([
            'user_id' => $user->id,
            'successful_rows' => 5,
            'total_rows' => 7,
            'file_disk' => 'local',
            'file_name' => 'export.csv',
            'exporter' => UserExporter::class,
        ]);

        $message = UserExporter::getCompletedNotificationBody($export);

        expect($message)->toBe('Your user export has completed and 5 rows exported. 2 rows failed to export.');
    });
});
