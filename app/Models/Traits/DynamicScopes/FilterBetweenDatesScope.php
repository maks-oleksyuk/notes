<?php

declare(strict_types=1);

namespace App\Models\Traits\DynamicScopes;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
trait FilterBetweenDatesScope
{
    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    protected function filterBetweenDates(
        Builder $query,
        string $column,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Builder {
        return $query->whereBetween($column, [
            $startDate->startOfDay(),
            $endDate->endOfDay(),
        ]);
    }
}
