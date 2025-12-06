<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Repositories\Contracts\Models\UserRepositoryInterface;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('User')]
final class UserController extends Controller
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @throws \Throwable
     */
    #[Endpoint('List users')]
    public function index(): ResourceCollection
    {
        return $this->userRepository
            ->query()
            ->latest('id')
            ->paginate()
            ->toResourceCollection(UserResource::class);
    }

    #[Endpoint('Create user')]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userRepository
            ->query()
            ->create($request->validated());

        return new UserResource($user)
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    #[Endpoint('Get user')]
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    #[Endpoint('Update user')]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user);
    }

    #[Endpoint('Delete user')]
    public function destroy(User $user): Response
    {
        $user->delete();

        return $this->responseFactory->noContent();
    }
}
