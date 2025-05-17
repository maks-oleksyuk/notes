<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUsernameCanBeSetAndRetrieved(): void
    {
        $user = new User();
        $user->setUsername('test_user');

        $this->assertSame('test_user', $user->getUsername());
        $this->assertSame('test_user', $user->getUserIdentifier());
    }

    public function testRolesDefaultIncludesRoleUser(): void
    {
        $user = new User();

        $this->assertContains(UserRole::USER->value, $user->getRoles());
    }

    public function testRolesCanBeSet(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertContains(UserRole::ADMIN->value, $user->getRoles());
        $this->assertContains(UserRole::USER->value, $user->getRoles());
        $this->assertCount(2, $user->getRoles());
    }

    public function testPasswordCanBeSetAndRetrieved(): void
    {
        $user = new User();
        $user->setPassword('secret');

        $this->assertSame('secret', $user->getPassword());
    }
}
