<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\Filters\Contracts\FiltersData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

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
        /** @var Builder<TModel> */
        return $this->model->newQuery();
    }

    public function find(int|string $id): ?Model
    {
        /** @var TModel|null */
        return $this->query()->find($id);
    }

    public function findAll(int $perPage = -1, int $page = 1): Collection|LengthAwarePaginator
    {
        if ($perPage === -1) {
            /** @var Collection<int, TModel> */
            return $this->query()->get();
        }

        /** @var LengthAwarePaginator<int, TModel> */
        return $this->query()->paginate(perPage: $perPage, page: $page);
    }

    public function findBy(FiltersData $filters, array $order = [], int $perPage = -1, int $page = 1): Collection|LengthAwarePaginator
    {
        $query = $this->getFilteredQuery($filters, $order);

        if ($perPage === -1) {
            /** @var Collection<int, TModel> */
            return $query->get();
        }

        /** @var LengthAwarePaginator<int, TModel> */
        return $query->paginate(perPage: $perPage, page: $page);
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
