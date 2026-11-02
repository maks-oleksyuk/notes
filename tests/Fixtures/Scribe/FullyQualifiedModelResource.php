<?php

declare(strict_types=1);

namespace Tests\Fixtures\Scribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Model resource whose docblock uses a fully qualified class name instead of an import.
 *
 * @property \App\Models\User $resource
 */
final class FullyQualifiedModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->resource->email,
        ];
    }
}
