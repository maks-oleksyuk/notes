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
 *
 * @implements BaseRepositoryInterface<TModel>
 */
abstract readonly class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(
        /** @var TModel */
        protected Model $model,
    ) {}

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function find(int|string $id): ?Model
    {
        /** @var TModel|null */
        return $this->query()->findOrFail($id);
    }

    public function findAll(int $perPage = -1, int $page = 1): Collection|LengthAwarePaginator
    {
        return $perPage === -1
            ? $this->query()->get()
            : $this->query()->paginate(perPage: $perPage, page: $page);
    }

    public function findBy(FiltersData $filters, array $order = [], int $perPage = -1, int $page = 1): Collection|LengthAwarePaginator
    {
        $query = $this->getFilteredQuery($filters, $order);

        return $perPage === -1
            ? $query->get()
            : $query->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  Builder<TModel>  $query
     * @param  array<int|string, string>  $order
     * @return Builder<TModel>
     */
    protected function addQueryOrder(Builder $query, array $order): Builder
    {
        foreach ($order as $column => $direction) {
            is_int($column)
                ? $query->orderBy($direction)
                : $query->orderBy($column, $direction);
        }

        return $query;
    }
}
