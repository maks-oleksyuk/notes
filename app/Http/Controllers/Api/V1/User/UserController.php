<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Group;

#[Group('User')]
final class UserController extends Controller
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
    ) {}

    /**
     * @throws \Throwable
     */
    public function index(): ResourceCollection
    {
        return User::query()->latest('id')->paginate()->toResourceCollection(UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return new UserResource($user)
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user);
    }

    public function destroy(User $user): Response
    {
        $user->delete();

        return $this->responseFactory->noContent();
    }
}
