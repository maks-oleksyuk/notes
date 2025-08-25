<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Traits\DynamicScopes;

use App\Models\Traits\DynamicScopes\FilterBetweenDatesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Note on stack:
 * - Testing library/framework: PHPUnit (Laravel)
 * - Mocking library: Mockery (commonly bundled with Laravel's test stack)
 *
 * We unit-test the trait's scope by:
 * - creating a stub model that uses the trait and exposes a public wrapper
 *   to call the protected scope method.
 * - mocking Eloquent\Builder to assert whereBetween is invoked with the
 *   expected column and Carbon instances normalized to the start/end of day.
 */
final class FilterBetweenDatesScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Stub model that uses the trait and exposes a public wrapper to the protected scope.
     *
     * @extends Model
     */
    private function makeStubModel(): object
    {
        return new class extends Model {
            use FilterBetweenDatesScope;
            // Expose the protected scope via a public wrapper purely for tests.
            public function callFilterBetweenDates(Builder $query, string $column, Carbon $start, Carbon $end): Builder
            {
                // Call the protected scope method inside the same class context.
                return $this->scopeFilterBetweenDates($query, $column, $start, $end);
            }
        };
    }

    public function test_it_applies_where_between_with_start_of_day_and_end_of_day(): void
    {
        $builder = Mockery::mock(Builder::class);

        $column = 'created_at';
        $start = Carbon::parse('2023-10-02 15:34:12', 'UTC');
        $end   = Carbon::parse('2023-10-05 04:03:02', 'UTC');

        $expectedStart = $start->copy()->startOfDay();
        $expectedEnd   = $end->copy()->endOfDay();

        // Ensure method chaining: whereBetween returns the builder instance.
        $builder->shouldReceive('whereBetween')
            ->once()
            ->with($column, Mockery::on(function ($range) use ($expectedStart, $expectedEnd) {
                return \is_array($range)
                    && \count($range) === 2
                    && $range[0] instanceof Carbon
                    && $range[1] instanceof Carbon
                    && $range[0]->equalTo($expectedStart)
                    && $range[1]->equalTo($expectedEnd);
            }))
            ->andReturn($builder);

        $model = $this->makeStubModel();
        $result = $model->callFilterBetweenDates($builder, $column, $start, $end);

        // Assert original Carbon instances are not mutated (trait uses copy()).
        $this->assertSame('15:34:12', $start->format('H:i:s'), 'Start Carbon should not be mutated');
        $this->assertSame('04:03:02', $end->format('H:i:s'), 'End Carbon should not be mutated');

        // Should return the builder for chaining
        $this->assertSame($builder, $result);
    }

    public function test_it_handles_single_day_range_expanding_to_full_day_inclusive(): void
    {
        $builder = Mockery::mock(Builder::class);

        $column = 'published_at';
        $day = Carbon::parse('2024-01-19 12:11:10', 'UTC');

        $expectedStart = $day->copy()->startOfDay();
        $expectedEnd   = $day->copy()->endOfDay();

        $builder->shouldReceive('whereBetween')
            ->once()
            ->with($column, Mockery::on(function ($range) use ($expectedStart, $expectedEnd) {
                return $range[0]->equalTo($expectedStart) && $range[1]->equalTo($expectedEnd);
            }))
            ->andReturn($builder);

        $model = $this->makeStubModel();
        $result = $model->callFilterBetweenDates($builder, $column, $day, $day);

        // Ensure initial $day is not mutated
        $this->assertSame('12:11:10', $day->format('H:i:s'));

        $this->assertSame($builder, $result);
    }

    public function test_it_does_not_reorder_reversed_dates_passes_through_as_is(): void
    {
        $builder = Mockery::mock(Builder::class);

        $column = 'updated_at';
        $start = Carbon::parse('2024-02-10 08:00:00', 'UTC');
        $end   = Carbon::parse('2024-02-01 18:30:00', 'UTC'); // end earlier than start

        $expectedStart = $start->copy()->startOfDay();
        $expectedEnd   = $end->copy()->endOfDay();

        // The trait does not reorder; it passes the two values as given (after day-boundary normalization).
        $builder->shouldReceive('whereBetween')
            ->once()
            ->with($column, Mockery::on(function ($range) use ($expectedStart, $expectedEnd) {
                return $range[0]->equalTo($expectedStart) && $range[1]->equalTo($expectedEnd);
            }))
            ->andReturn($builder);

        $model = $this->makeStubModel();
        $result = $model->callFilterBetweenDates($builder, $column, $start, $end);

        // Original instances unchanged
        $this->assertSame('08:00:00', $start->format('H:i:s'));
        $this->assertSame('18:30:00', $end->format('H:i:s'));
        $this->assertSame($builder, $result);
    }

    public function test_it_works_with_custom_column_names_and_table_prefixes(): void
    {
        $builder = Mockery::mock(Builder::class);

        $column = 'posts.published_at'; // table-qualified column
        $start = Carbon::parse('2022-07-04 00:00:01', 'UTC');
        $end   = Carbon::parse('2022-07-10 23:59:59', 'UTC');

        $expectedStart = $start->copy()->startOfDay();
        $expectedEnd   = $end->copy()->endOfDay();

        $builder->shouldReceive('whereBetween')
            ->once()
            ->with($column, Mockery::on(function ($range) use ($expectedStart, $expectedEnd) {
                return $range[0]->equalTo($expectedStart) && $range[1]->equalTo($expectedEnd);
            }))
            ->andReturn($builder);

        $model = $this->makeStubModel();
        $result = $model->callFilterBetweenDates($builder, $column, $start, $end);

        $this->assertSame($builder, $result);
    }
}
