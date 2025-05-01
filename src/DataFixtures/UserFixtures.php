<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    /**
     * @var list<array{
     *     username: non-empty-string,
     *     password: string,
     *     roles: string[]
     * }>
     */
    private array $usersData = [[
        'username' => 'admin',
        'password' => 'admin',
        'roles' => [Role::ADMIN->value],
    ], [
        'username' => 'test',
        'password' => 'test',
        'roles' => [Role::USER->value],
    ]];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->usersData as $data) {
            $user = new User()
                ->setUsername($data['username'])
                ->setRoles($data['roles'])
                ->setPassword($this->passwordHasher->hashPassword(new User(), $data['password']));

            $manager->persist($user);
        }

        $manager->flush();
    }
}
