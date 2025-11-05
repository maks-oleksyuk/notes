<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

covers(UserResource::class);

it('returns expected user data array', function (): void {
    $user = new User([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $resource = new UserResource($user);

    expect($resource->toArray(new Request))->toBe([
        'id' => $user->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});
