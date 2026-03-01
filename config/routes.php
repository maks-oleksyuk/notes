<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

use Symfony\Component\HttpFoundation\Request;

return Routes::config([
    'controllers' => [
        'resource' => '../src/Controller/',
        'type' => 'attribute',
    ],
    'app_v1_docs_json' => [
        'path' => '/api/docs.json',
        'controller' => 'nelmio_api_doc.controller.swagger_json',
        'methods' => [Request::METHOD_GET],
    ],
    'api_v1_login' => [
        'path' => '/api/v1/login',
        'methods' => [Request::METHOD_POST],
    ],
]);
