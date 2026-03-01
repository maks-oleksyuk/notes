<?php

declare(strict_types=1);

namespace App\Tests\Integration\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(UserFixtures::class)]
final class UserFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testLoadCreatesExpectedUsers(): void
    {
        $fixtures = new UserFixtures($this->passwordHasher);
        $fixtures->load($this->em);

        $users = $this->userRepository->findAll();

        $this->assertCount(2, $users);

        $admin = $this->userRepository->findOneByUsername('admin');
        $this->assertInstanceOf(User::class, $admin);
        $this->assertContains(UserRole::ADMIN->value, $admin->getRoles());
        $this->assertTrue($this->passwordHasher->isPasswordValid($admin, 'admin'));

        $testUser = $this->userRepository->findOneByUsername('test');
        $this->assertInstanceOf(User::class, $testUser);
        $this->assertContains(UserRole::USER->value, $testUser->getRoles());
        $this->assertTrue($this->passwordHasher->isPasswordValid($testUser, 'test'));
    }
}
