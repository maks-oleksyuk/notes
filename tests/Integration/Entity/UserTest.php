<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(User::class)]
final class UserTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->validator = $container->get(ValidatorInterface::class);
        $this->em = $container->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testValidUser(): void
    {
        $user = new User()->setUsername('valid_user');
        $violations = $this->validator->validate($user);

        self::assertCount(0, $violations);
    }

    public function testShortUsername(): void
    {
        $user = new User()->setUsername('ab');
        $violations = $this->validator->validate($user);

        self::assertCount(1, $violations);
        self::assertSame('Username must be at least 3 characters', $violations->get(0)->getMessage());
    }

    public function testUniqueUsernameConstraint(): void
    {
        $user1 = new User()->setUsername('duplicate')->setPassword('pass');
        $user2 = new User()->setUsername('duplicate')->setPassword('pass');

        $this->em->persist($user1);
        $this->em->flush();

        $violations = $this->validator->validate($user2);

        self::assertCount(1, $violations);
        self::assertSame('There is already an account with this username', $violations->get(0)->getMessage());
    }
}
