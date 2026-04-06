<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return Routes::config([
    'easyadmin' => [
        'resource' => '.',
        'type' => 'easyadmin.routes',
    ],
]);
