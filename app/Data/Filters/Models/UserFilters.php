<?php

declare(strict_types=1);

namespace App\Data\Filters\Models;

use App\Data\Filters\Contracts\FiltersData;
use Spatie\LaravelData\Data;

final class UserFilters extends Data implements FiltersData
{
    /**
     * @param  array<int>  $ids
     */
    public function __construct(
        public ?array $ids = null,
    ) {}
}
