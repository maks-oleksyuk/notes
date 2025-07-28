<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->add('app.swagger_ui', '/api/docs')
        ->methods([Request::METHOD_GET])
        ->controller('nelmio_api_doc.controller.swagger_ui');
};
