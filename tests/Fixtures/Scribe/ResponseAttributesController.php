<?php

declare(strict_types=1);

namespace Tests\Fixtures\Scribe;

use App\Http\Resources\Api\V1\Auth\TokenResource;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * Controller double whose actions only carry Scribe response attributes.
 */
final class ResponseAttributesController
{
    #[ResponseFromApiResource(TokenResource::class, status: HttpFoundationResponse::HTTP_CREATED, description: 'Created')]
    public function arrayResource(): void {}

    #[ResponseFromApiResource(ShapeResource::class)]
    public function shapeResource(): void {}

    #[ResponseFromApiResource(UserResource::class, User::class)]
    public function modelResource(): void {}

    #[ResponseFromApiResource(UserResource::class)]
    public function inferredModelResource(): void {}

    #[ResponseFromApiResource(FullyQualifiedModelResource::class)]
    public function fullyQualifiedModelResource(): void {}

    #[ResponseFromApiResource(PlainResource::class)]
    public function plainResource(): void {}

    #[ResponseFromApiResource(UntypedResource::class)]
    public function untypedResource(): void {}
}
