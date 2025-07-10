<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api\V1;

use App\Api\V1\Controller\UserApiController;
use App\Api\V1\Dto\Request\PaginationQueryDto;
use App\Api\V1\Dto\Resource\User\UserResourceDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Helper\UserCreatorTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

#[CoversClass(UserApiController::class)]
#[CoversClass(UserResourceDto::class)]
#[CoversClass(PaginationQueryDto::class)]
final class UserApiControllerTest extends KernelTestCase
{
    use UserCreatorTrait;

    private UserApiController $controller;

    private UserRepository $userRepository;

    private ObjectMapperInterface&MockObject $mapper;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->userRepository = $container->get(UserRepository::class);

        $this->createUsers($em, 2);

        $this->mapper = $this->createMock(ObjectMapperInterface::class);
        $this->controller = new UserApiController($this->userRepository, $this->mapper);
        $this->controller->setContainer($container);
    }

    public function testIndexCustomPagination(): void
    {
        $dto = new PaginationQueryDto(page: 2, limit: 1);
        $users = $this->userRepository->paginate(page: 2, limit: 1);

        $expectedItem = new UserResourceDto(
            id: $users[0]->getId(),
            username: $users[0]->getUsername(),
        );

        $this->mapper
            ->expects(self::once())
            ->method('map')
            ->with($users[0], UserResourceDto::class)
            ->willReturn($expectedItem);

        $response = $this->controller->index($dto);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('data', $data);
        self::assertIsArray($data['data']);
        self::assertCount(1, $data['data']);
        self::assertSame([
            'id' => $expectedItem->id,
            'username' => $expectedItem->username,
        ], $data['data'][0]);
    }

    public function testShow(): void
    {
        $user = $this->userRepository->findOneByUsername('user1');
        self::assertInstanceOf(User::class, $user);
        $dto = new UserResourceDto(
            id: $user->getId(),
            username: 'user1',
        );

        $this->mapper
            ->expects(self::once())
            ->method('map')
            ->with($user, UserResourceDto::class)
            ->willReturn($dto);

        $response = $this->controller->show($user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['data' => ['id' => $dto->id, 'username' => $dto->username]],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testCreate(): void
    {
        $response = $this->controller->create();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertEmpty(json_decode((string) $response->getContent(), true));
    }

    public function testUpdate(): void
    {
        $user = $this->userRepository->findOneByUsername('user1');
        self::assertInstanceOf(User::class, $user);

        $response = $this->controller->update($user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertEmpty(json_decode((string) $response->getContent(), true));
    }

    public function testDelete(): void
    {
        $user = $this->userRepository->findOneByUsername('user1');
        self::assertInstanceOf(User::class, $user);
        $response = $this->controller->delete($user);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertEmpty(json_decode((string) $response->getContent(), true));
    }
}
