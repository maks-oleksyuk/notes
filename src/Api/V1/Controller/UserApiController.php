<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Dto\Request\PaginationQueryDto;
use App\Api\V1\Dto\Resource\User\UserResourceDto;
use App\Entity\User;
use App\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'User')]
final class UserApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    #[OA\Get(summary: 'List users')]
    #[Route(path: '/users', name: 'user_index', methods: [Request::METHOD_GET], format: 'json')]
    public function index(#[MapQueryString] PaginationQueryDto $pagination): JsonResponse
    {
        return $this->json(['data' => array_map(
            fn (User $user): object => $this->objectMapper->map($user, UserResourceDto::class),
            $this->userRepository->paginate(page: $pagination->page, limit: $pagination->limit),
        )]);
    }

    #[OA\Get(summary: 'Get user')]
    #[Route(path: '/users/{id}', name: 'user_show', methods: [Request::METHOD_GET], format: 'json')]
    public function show(User $user): JsonResponse
    {
        return $this->json([
            'data' => $this->objectMapper->map($user, UserResourceDto::class),
        ]);
    }

    #[OA\Post(summary: 'Create user')]
    #[Route(path: '/users', name: 'user_create', methods: [Request::METHOD_POST], format: 'json')]
    public function create(): JsonResponse
    {
        return $this->json([], Response::HTTP_CREATED);
    }

    #[OA\Put(summary: 'Update user')]
    #[OA\Patch(summary: 'Update user')]
    #[Route(path: '/users/{id}', name: 'user_update', methods: [Request::METHOD_PUT, Request::METHOD_PATCH], format: 'json')]
    public function update(User $user): JsonResponse
    {
        return $this->json([]);
    }

    #[OA\Delete(summary: 'Delete user')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'User deleted.')]
    #[Route(path: '/users/{id}', name: 'user_delete', methods: [Request::METHOD_DELETE], format: 'json')]
    public function delete(User $user): JsonResponse
    {
        return $this->json([], Response::HTTP_NO_CONTENT);
    }
}
