<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;

class UserTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->validator = $container->get(ValidatorInterface::class);
        $this->em = $container->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testValidUser(): void
    {
        $user = (new User())
            ->setUsername('validuser')
            ->setPassword('password');

        $violations = $this->validator->validate($user);
        $this->assertCount(0, $violations);
    }

    public function testShortUsername(): void
    {
        $user = (new User())
            ->setUsername('ab') // too short
            ->setPassword('123');

        $violations = $this->validator->validate($user);
        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('Username must be at least 3 characters', $violations[0]->getMessage());
    }

    public function testUniqueUsernameConstraint(): void
    {
        $user1 = (new User())
            ->setUsername('duplicate')
            ->setPassword('pwd');

        $user2 = (new User())
            ->setUsername('duplicate')
            ->setPassword('pwd');

        $this->em->persist($user1);
        $this->em->flush();

        $violations = $this->validator->validate($user2);
        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('There is already an account with this username', $violations[0]->getMessage());
    }
}
