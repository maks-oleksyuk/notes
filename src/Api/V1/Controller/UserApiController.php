<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Dto\Resource\User\UserResourceDto;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserApiController extends AbstractController
{
    public function __construct(
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    #[Route(path: '/users', name: 'user_index', methods: [Request::METHOD_GET], format: 'json')]
    public function index(): JsonResponse
    {
        return $this->json([]);
    }

    #[Route(path: '/users/{id}', name: 'user_show', methods: [Request::METHOD_GET], format: 'json')]
    public function show(User $user): JsonResponse
    {
        return $this->json(
            $this->objectMapper->map($user, UserResourceDto::class)
        );
    }

    #[Route(path: '/users', name: 'user_create', methods: [Request::METHOD_POST], format: 'json')]
    public function create(): JsonResponse
    {
        return $this->json([]);
    }

    #[Route(path: '/users/{id}', name: 'user_update', methods: [Request::METHOD_PUT, Request::METHOD_PATCH], format: 'json')]
    public function update(User $user): JsonResponse
    {
        return $this->json([]);
    }

    #[Route(path: '/users/{id}', name: 'user_delete', methods: [Request::METHOD_DELETE], format: 'json')]
    public function delete(User $user): JsonResponse
    {
        return $this->json(data: [], status: Response::HTTP_NO_CONTENT);
    }
}
