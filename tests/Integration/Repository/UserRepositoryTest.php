<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Helper\UserCreatorTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends KernelTestCase
{
    use UserCreatorTrait;

    private EntityManagerInterface $em;

    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(UserRepository::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testFindOneByUsernameReturnsUserWhenExists(): void
    {
        $user = new User()
            ->setUsername('maks')
            ->setPassword('password');

        $this->em->persist($user);
        $this->em->flush();

        $found = $this->repository->findOneByUsername('maks');

        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('maks', $found->getUsername());
    }

    public function testFindOneByUsernameReturnsNullWhenNotExists(): void
    {
        $result = $this->repository->findOneByUsername('ghost');

        $this->assertNotInstanceOf(User::class, $result);
    }

    public function testPaginateReturnsCorrectUsersForPages(): void
    {
        $this->createUsers($this->em, 5);

        $result = $this->repository->paginate(1, 2);
        $this->assertCount(2, $result);
        $this->assertSame('user1', $result[0]->getUsername());
        $this->assertSame('user2', $result[1]->getUsername());

        $result = $this->repository->paginate(2, 2);
        $this->assertCount(2, $result);
        $this->assertSame('user3', $result[0]->getUsername());
        $this->assertSame('user4', $result[1]->getUsername());

        $result = $this->repository->paginate(5, 2);
        $this->assertEmpty($result);
    }

    public function testUpgradePasswordThrowsForUnsupportedUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $unsupportedUser = $this->createStub(PasswordAuthenticatedUserInterface::class);
        $this->repository->upgradePassword($unsupportedUser, 'new_hashed_password');
    }

    public function testUpgradePasswordPersistsNewPassword(): void
    {
        $user = new User()->setUsername('user')->setPassword('old_password');

        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'new_hashed_password');

        $reloaded = $this->repository->find($user->getId());
        $this->assertSame('new_hashed_password', $reloaded?->getPassword());
    }
}
