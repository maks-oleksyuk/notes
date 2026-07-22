<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

use Symfony\Component\HttpFoundation\Request;

return Routes::config([
    'app.swagger_ui' => [
        'path' => '/api/docs',
        'methods' => [Request::METHOD_GET],
        'controller' => 'nelmio_api_doc.controller.scalar',
    ],
]);
