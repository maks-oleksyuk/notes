<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\Filters\Contracts\FiltersData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface BaseRepositoryInterface
{
    /**
     * @return Builder<TModel|Model>
     */
    public function query(): Builder;

    /**
     * @return TModel|Model
     */
    public function find(int|string $id): ?Model;

    /**
     * @return Collection<int, TModel|Model>|LengthAwarePaginator<TModel|Model>
     */
    public function getAll(int $perPage = -1, int $page = 1): Collection|LengthAwarePaginator;

    /**
     * @param  array<int|string, string>  $order
     * @return Collection<int, TModel|Model>|LengthAwarePaginator<TModel|Model>
     */
    public function getFiltered(
        FiltersData $filters,
        array $order = [],
        int $perPage = -1,
        int $page = 1,
    ): Collection|LengthAwarePaginator;

    /**
     * @param  array<int|string, string>  $order
     * @return Builder<TModel|Model>
     */
    public function getFilteredQuery(FiltersData $filters, array $order = []): Builder;
}
