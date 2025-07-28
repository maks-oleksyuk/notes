<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import(
        resource: '../../src/Api/V1/Controller/',
        type: 'attribute'
    )
        ->prefix('/api/v1')
        ->namePrefix('api_v1_');
};
