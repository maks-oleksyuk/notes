<?php

declare(strict_types=1);

namespace App\Models\Traits\DynamicScopes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @mixin Model
 */
trait FilterBetweenDatesScope
{
    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeFilterBetweenDates(
        Builder $query,
        string $column,
        string $start_date,
        string $end_date,
    ): Builder {
        return $query
            ->where($column, '>=', Carbon::parse($start_date)->startOfDay())
            ->where($column, '<=', Carbon::parse($end_date)->endOfDay());
    }
}
