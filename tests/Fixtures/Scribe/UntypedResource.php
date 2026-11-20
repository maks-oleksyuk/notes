<?php

declare(strict_types=1);

namespace Tests\Fixtures\Scribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource whose docblock has no `@property ... $resource` tag.
 *
 * @property int $other
 */
final class UntypedResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['untyped' => true];
    }
}
