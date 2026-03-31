<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\PaginatedRequest;

final class IndexUserRequest extends PaginatedRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
