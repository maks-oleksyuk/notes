<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Dto\Request\PaginationQueryDto;
use App\Api\V1\Dto\Resource\User\UserResourceDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
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

    // @codeCoverageIgnoreStart
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Returns the list of users.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: UserResourceDto::class))
                ),
            ],
            type: 'object'
        )
    )]
    // @codeCoverageIgnoreEnd
    #[Route(path: '/users', name: 'user_index', methods: [Request::METHOD_GET], format: 'json')]
    public function index(
        #[MapQueryString]
        PaginationQueryDto $pagination,
    ): JsonResponse {
        return $this->json(['data' => array_map(
            fn (User $user): object => $this->objectMapper->map($user, UserResourceDto::class),
            $this->userRepository->paginate(page: $pagination->page, limit: $pagination->limit),
        )]);
    }

    // @codeCoverageIgnoreStart
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Returns the user resource.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: new Model(type: UserResourceDto::class),
                ),
            ],
            type: 'object',
        )
    )]
    // @codeCoverageIgnoreEnd
    #[Route(path: '/users/{id}', name: 'user_show', methods: [Request::METHOD_GET], format: 'json')]
    public function show(User $user): JsonResponse
    {
        return $this->json([
            'data' => $this->objectMapper->map($user, UserResourceDto::class),
        ]);
    }

    // @codeCoverageIgnoreStart
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Returns the user resource.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: new Model(type: UserResourceDto::class),
                ),
            ],
            type: 'object',
        )
    )]
    // @codeCoverageIgnoreEnd
    #[Route(path: '/users', name: 'user_create', methods: [Request::METHOD_POST], format: 'json')]
    public function create(): JsonResponse
    {
        return $this->json([], Response::HTTP_CREATED);
    }

    // @codeCoverageIgnoreStart
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Returns the user resource.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: new Model(type: UserResourceDto::class),
                ),
            ],
            type: 'object',
        )
    )]
    // @codeCoverageIgnoreEnd
    #[Route(path: '/users/{id}', name: 'user_update', methods: [Request::METHOD_PUT, Request::METHOD_PATCH], format: 'json')]
    public function update(User $user): JsonResponse
    {
        return $this->json([]);
    }

    // @codeCoverageIgnoreStart
    #[OA\Response(
        response: Response::HTTP_NO_CONTENT,
        description: 'User deleted.'
    )]
    // @codeCoverageIgnoreEnd
    #[Route(path: '/users/{id}', name: 'user_delete', methods: [Request::METHOD_DELETE], format: 'json')]
    public function delete(User $user): JsonResponse
    {
        return $this->json(data: [], status: Response::HTTP_NO_CONTENT);
    }
}
