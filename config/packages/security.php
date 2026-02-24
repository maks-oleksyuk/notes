<?php

declare(strict_types=1);

use App\Entity\User;
use App\Enum\UserRole;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return [
    'security' => [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],
        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'username',
                ],
            ],
        ],
        'firewalls' => [
            'api_v1_login' => [
                'pattern' => '^/api/v1/login',
                'stateless' => true,
                'json_login' => [
                    'check_path' => 'api_v1_login',
                    'success_handler' => AuthenticationSuccessHandler::class,
                    'failure_handler' => AuthenticationFailureHandler::class,
                ],
            ],
            'api' => [
                'pattern' => '^/api/v',
                'stateless' => true,
                'provider' => 'app_user_provider',
                'jwt' => [],
            ],
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                    'enable_csrf' => true,
                ],
                'logout' => [
                    'path' => 'app_logout',
                ],
            ],
        ],
        'access_control' => [
            ['path' => 'admin', 'roles' => UserRole::ADMIN->value],
            ['path' => '^/api/v1/login', 'roles' => AuthenticatedVoter::PUBLIC_ACCESS],
            ['path' => '^/api/v', 'roles' => AuthenticatedVoter::IS_AUTHENTICATED_FULLY],
        ],
    ],
];
