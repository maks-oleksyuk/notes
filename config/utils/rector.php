<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../../config',
        __DIR__.'/../../src',
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
    )
    ->withSets([
        SymfonySetList::SYMFONY_72,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon'])
    ->withSymfonyContainerXml(__DIR__.'/../../var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withParallel()
    ->withCache(
        __DIR__.'/../../var/cache/rector',
        FileCacheStorage::class
    );
