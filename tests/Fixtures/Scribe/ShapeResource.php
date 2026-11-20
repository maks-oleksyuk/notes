<?php

declare(strict_types=1);

namespace Tests\Fixtures\Scribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Array-backed resource covering the docblock types the Scribe strategy turns into examples.
 *
 * @property array{
 *     tags: list<string>,
 *     rows: array{id: int}[],
 *     count: ?int,
 *     score: null|int,
 *     'quoted': bool,
 *     9: string,
 *     pair: array{int, bool},
 *     email: string,
 *     items?: array<string, array{id: int}>,
 *     created_at: string,
 * } $resource
 */
final class ShapeResource extends JsonResource
{
    /**
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
