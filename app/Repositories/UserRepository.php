<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Filters\Contracts\FiltersData;
use App\Data\Filters\Models\UserFilters;
use App\Models\User;
use App\Repositories\Contracts\BaseRepository;
use App\Repositories\Contracts\Models\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<User>
 */
final readonly class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * @param  UserFilters  $filters
     * @param  array<int|string, \SortDirection|'asc'|'desc'>  $order
     */
    public function getFilteredQuery(UserFilters|FiltersData $filters, array $order = []): Builder
    {
        $query = $this->query()
            ->when($filters->ids,
                fn (Builder $query) => $query->whereIn('id', $filters->ids)
            );

        return $this->addQueryOrder($query, $order);
    }
}
