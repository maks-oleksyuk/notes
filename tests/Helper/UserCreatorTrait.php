<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait UserCreatorTrait
{
    /**
     * @param non-empty-string $username
     * @param array<string>    $roles
     */
    protected function createUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        string $username = 'username',
        string $password = 'password',
        array $roles = [],
    ): User {
        $user = new User()
            ->setUsername($username)
            ->setPassword($hasher->hashPassword(new User(), $password))
            ->setRoles($roles);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function createAdminUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): User {
        return $this->createUser($em, $hasher, 'admin', 'admin', [UserRole::ADMIN->value]);
    }

    protected function createTestUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): User {
        return $this->createUser($em, $hasher, 'test', 'test');
    }
}
