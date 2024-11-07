<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../../app',
        __DIR__.'/../../database',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_110,
    ]);
