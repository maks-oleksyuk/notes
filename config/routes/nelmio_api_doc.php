<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return Routes::config([
    'app.swagger_ui' => [
        'path' => '/api/docs',
        'methods' => ['GET'],
        'controller' => 'nelmio_api_doc.controller.swagger_ui',
    ],
]);
