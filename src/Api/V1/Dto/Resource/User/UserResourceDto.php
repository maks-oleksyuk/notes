<?php

declare(strict_types=1);

namespace App\Api\V1\Dto\Resource\User;

use App\Entity\User;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: User::class)]
final class UserResourceDto
{
    public function __construct(
        #[Map(target: 'id')]
        public int $id,
        #[Map(target: 'username')]
        public string $username,
    ) {
    }
}
