<?php

declare(strict_types=1);

namespace Tests\Fixtures\Scribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PlainResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'plain' => true,
        ];
    }
}
