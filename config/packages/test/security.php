<?php

declare(strict_types=1);

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return [
    'security' => [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => [
                'algorithm' => 'auto',
                'cost' => 4,
                'time_cost' => 3,
                'memory_cost' => 10,
            ],
        ],
    ],
];
