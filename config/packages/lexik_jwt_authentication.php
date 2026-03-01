<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'lexik_jwt_authentication' => [
        'secret_key' => env('JWT_SECRET_KEY')->resolve(),
        'public_key' => env('JWT_PUBLIC_KEY')->resolve(),
        'pass_phrase' => env('JWT_PASSPHRASE'),
    ],
]);
