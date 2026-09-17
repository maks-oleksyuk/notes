<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
final class User implements PasswordAuthenticatedUserInterface, UserInterface
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public private(set) int $id;

    /** @var non-empty-string */
    #[Assert\Length(min: 3, minMessage: 'Username must be at least {{ limit }} characters')]
    #[Assert\Regex(pattern: '/^\S+$/', message: 'Username must not contain spaces')]
    #[ORM\Column(length: 30)]
    public string $username;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    public array $roles = [] {
        // guarantee every user at least has ROLE_USER
        get => array_values(array_unique([...$this->roles, UserRole::USER->value]));
    }

    #[ORM\Column(nullable: true)]
    public ?string $password = null;

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
