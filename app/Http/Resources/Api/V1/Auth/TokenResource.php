<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string[] $resource
 */
final class TokenResource extends JsonResource
{
    /**
     * @return string[]
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'token' => $resource['token'] ?? '',
            'token_type' => 'Bearer',
            'expires_at' => $resource['expires_at'] ?? '',
        ];
    }
}
