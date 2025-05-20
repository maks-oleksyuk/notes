<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testGetId(): void
    {
        $reflection = new \ReflectionClass(User::class);
        $property = $reflection->getProperty('id');

        $user = new User();
        $property->setValue($user, 123);

        self::assertSame(123, $user->getId());
    }

    public function testUsernameCanBeSetAndRetrieved(): void
    {
        $user = new User();
        $user->setUsername('test_user');

        self::assertSame('test_user', $user->getUsername());
        self::assertSame('test_user', $user->getUserIdentifier());
    }

    public function testRolesCanBeSetAndRetrieved(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        self::assertContains(UserRole::ADMIN->value, $user->getRoles());
        // every user at least has ROLE_USER
        self::assertContains(UserRole::USER->value, $user->getRoles());
        self::assertCount(2, $user->getRoles());
    }

    public function testPasswordCanBeSetAndRetrieved(): void
    {
        $user = new User();

        self::assertNull($user->getPassword());

        $user->setPassword('secret');

        $user->eraseCredentials();

        self::assertSame('secret', $user->getPassword());
    }
}
