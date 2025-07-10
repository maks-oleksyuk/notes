<?php

declare(strict_types=1);

use Symfony\Config\LexikJwtAuthenticationConfig;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (LexikJwtAuthenticationConfig $lexikJwtAuthenticationConfig): void {
    $lexikJwtAuthenticationConfig
        ->secretKey(env('JWT_SECRET_KEY')->resolve())
        ->publicKey(env('JWT_PUBLIC_KEY')->resolve())
        ->passPhrase(env('JWT_PASSPHRASE'));
};
