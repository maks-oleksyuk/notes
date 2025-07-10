<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import([
        'path' => '../src/Controller/',
        'namespace' => 'App\Controller',
    ], 'attribute');

    $routingConfigurator
        ->add('app_v1_docs_json', '/api/docs.json')
        ->controller('nelmio_api_doc.controller.swagger_json')
        ->methods([Request::METHOD_GET]);

    $routingConfigurator
        ->add('api_v1_login', '/api/v1/login')
        ->methods([Request::METHOD_POST]);
};
