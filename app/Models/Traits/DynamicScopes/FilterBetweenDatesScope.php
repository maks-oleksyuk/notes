<?php

declare(strict_types=1);

namespace App\Models\Traits\DynamicScopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @template TModel of Model
 */
trait FilterBetweenDatesScope
{
    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scopeFilterBetweenDates(
        Builder $query,
        string $column,
        Carbon $startDate,
        Carbon $endDate,
    ): Builder {
        return $query->whereBetween($column, [
            $startDate->copy()->startOfDay(),
            $endDate->copy()->endOfDay(),
        ]);
    }
}
