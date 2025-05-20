<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->repository = self::getContainer()->get(UserRepository::class);
        $this->em = $container->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testUpgradePasswordThrowsForUnsupportedUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $unsupportedUser = $this->createMock(PasswordAuthenticatedUserInterface::class);
        $this->repository->upgradePassword($unsupportedUser, 'new_hashed_password');
    }

    public function testUpgradePasswordPersistsNewPassword(): void
    {
        $user = new User()->setUsername('user')->setPassword('old_password');

        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'new_hashed_password');

        $reloaded = $this->repository->find($user->getId());
        self::assertSame('new_hashed_password', $reloaded?->getPassword());
    }
}
