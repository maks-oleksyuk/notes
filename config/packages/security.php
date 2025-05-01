<?php

declare(strict_types=1);

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Config\Security\PasswordHasherConfig;
use Symfony\Config\SecurityConfig;

return static function (ContainerConfigurator $containerConfigurator, SecurityConfig $securityConfig): void {
    $securityConfig->passwordHasher(PasswordAuthenticatedUserInterface::class, 'auto');

    $securityConfig
        ->provider('app_user_provider')
        ->entity()
        ->class(User::class)
        ->property('username');

    $securityConfig
        ->firewall('dev')
        ->pattern('^/(_(profiler|wdt)|css|images|js)/')
        ->security(false);

    $mainFirewall = $securityConfig->firewall('main');

    $mainFirewall
        ->lazy(true)
        ->provider('app_user_provider');

    $mainFirewall
        ->formLogin()
        ->loginPath('app_login')
        ->checkPath('app_login')
        ->enableCsrf(true);

    $mainFirewall
        ->logout()
        ->path('app_logout');

    $securityConfig
        ->accessControl()
        ->path('admin')
        ->roles(Role::ADMIN->value);

    if ('test' === $containerConfigurator->env()) {
        $passwordHasher = $securityConfig->passwordHasher(PasswordAuthenticatedUserInterface::class);
        assert($passwordHasher instanceof PasswordHasherConfig);

        $passwordHasher
            ->algorithm('auto')
            ->cost(4)
            ->timeCost(3)
            ->memoryCost(10)
        ;
    }
};
