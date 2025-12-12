<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

pest()->use(RefreshDatabase::class);

describe('Between Dates Scope ', function (): void {
    it('filters models between dates', function (): void {
        $startDate = Date::parse('2025-01-01');
        $endDate = Date::parse('2025-01-31');

        $in1 = User::factory()->create(['created_at' => '2025-01-10']);
        $in2 = User::factory()->create(['created_at' => '2025-01-20']);
        $out = User::factory()->create(['created_at' => '2025-02-10']);

        $ids = User::query()
            ->filterBetweenDates('created_at', $startDate, $endDate)
            ->get(['id'])
            ->pluck('id');

        expect($ids)->toHaveCount(2)
            ->toContain($in1->id)
            ->toContain($in2->id)
            ->not->toContain($out->id);
    });

    it('filters models for a single day range', function (): void {
        $date = Date::parse('2025-01-01');

        $in = User::factory()->create(['created_at' => '2025-01-01 12:00:00']);
        $out = User::factory()->create(['created_at' => '2025-01-02 00:00:01']);

        $ids = User::query()
            ->filterBetweenDates('created_at', $date, $date)
            ->get(['id'])
            ->pluck('id');

        expect($ids)->toHaveCount(1)
            ->toContain($in->id)
            ->not->toContain($out->id);
    });
});
