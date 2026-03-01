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

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->userRepository = $container->get(UserRepository::class);

        $this->createUsers($em, 2);

        $mapper = $this->createStub(ObjectMapperInterface::class);
        $this->controller = new UserApiController($this->userRepository, $mapper);
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

        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper
            ->expects($this->once())
            ->method('map')
            ->with($users[0], UserResourceDto::class)
            ->willReturn($expectedItem);

        $controller = new UserApiController($this->userRepository, $mapper);
        $controller->setContainer(self::getContainer());

        $response = $controller->index($dto);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertCount(1, $data['data']);
        $this->assertSame([
            'id' => $expectedItem->id,
            'username' => $expectedItem->username,
        ], $data['data'][0]);
    }

    public function testShow(): void
    {
        $user = $this->userRepository->findOneByUsername('user1');
        $this->assertInstanceOf(User::class, $user);
        $dto = new UserResourceDto(
            id: $user->getId(),
            username: 'user1',
        );

        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper
            ->expects($this->once())
            ->method('map')
            ->with($user, UserResourceDto::class)
            ->willReturn($dto);

        $controller = new UserApiController($this->userRepository, $mapper);
        $controller->setContainer(self::getContainer());

        $response = $controller->show($user);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(['data' => ['id' => $dto->id, 'username' => $dto->username]], json_decode((string) $response->getContent(), true));
    }

    public function testCreate(): void
    {
        $response = $this->controller->create();

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertEmpty(json_decode((string) $response->getContent(), true));
    }

    public function testUpdate(): void
    {
        $user = $this->userRepository->findOneByUsername('user1');
        $this->assertInstanceOf(User::class, $user);

        $response = $this->controller->update($user);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertEmpty(json_decode((string) $response->getContent(), true));
    }

    public function testDelete(): void
    {
        $user = $this->userRepository->findOneByUsername('user1');
        $this->assertInstanceOf(User::class, $user);
        $response = $this->controller->delete($user);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertEmpty(json_decode((string) $response->getContent(), true));
    }
}
