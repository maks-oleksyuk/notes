<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Transform\Rector\StaticCall\StaticCallToMethodCallRector;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\Class_\RemoveModelPropertyFromFactoriesRector;
use RectorLaravel\Rector\Class_\UseForwardsCallsTraitRector;
use RectorLaravel\Rector\Empty_\EmptyToBlankAndFilledFuncRector;
use RectorLaravel\Rector\FuncCall\ArgumentFuncCallToMethodCallRector;
use RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector;
use RectorLaravel\Rector\MethodCall\ResponseHelperCallToJsonResponseRector;
use RectorLaravel\Rector\MethodCall\UseComponentPropertyWithinCommandsRector;
use RectorLaravel\Rector\MethodCall\WhereToWhereLikeRector;
use RectorLaravel\Rector\StaticCall\RequestStaticValidateToInjectRector;
use RectorLaravel\Rector\StaticCall\RouteActionCallableRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;
use RectorPest\Set\PestLevelSetList;
use RectorPest\Set\PestSetList;

if (defined('ARTISAN_BINARY') || ! class_exists(RectorConfig::class)) {
    return;
}

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../../app',
        __DIR__.'/../../bootstrap/app.php',
        __DIR__.'/../../bootstrap/providers.php',
        __DIR__.'/../../config',
        __DIR__.'/../../database',
        __DIR__.'/../../routes',
        __DIR__.'/../../tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withPhpSets(php85: true)
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(
        laravel: true,
    )
    ->withImportNames(
        importShortClasses: false,
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        // naming: true,
        // namedArgs: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
    )
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
        LaravelSetList::LARAVEL_STATIC_TO_INJECTION,
        LaravelSetList::LARAVEL_TESTING,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
        PestLevelSetList::UP_TO_PEST_40,
        PestSetList::PEST_CODE_QUALITY,
        PestSetList::PEST_CHAIN,
        PestSetList::PEST_LARAVEL,
        PestSetList::PEST_BROWSER,
    ])
    ->withConfiguredRule(RemoveDumpDataDeadCodeRector::class, [])
    ->withConfiguredRule(RouteActionCallableRector::class, [])
    ->withConfiguredRule(WhereToWhereLikeRector::class, [
        WhereToWhereLikeRector::USING_POSTGRES_DRIVER => true,
    ])
    ->withRules([
        RemoveModelPropertyFromFactoriesRector::class,
        ResponseHelperCallToJsonResponseRector::class,
        UseComponentPropertyWithinCommandsRector::class,
        UseForwardsCallsTraitRector::class,
        EmptyToBlankAndFilledFuncRector::class,
    ])
    ->withSkip([
        ArgumentFuncCallToMethodCallRector::class => [
            // Service providers are a poor fit for constructor injection — keep facades.
            __DIR__.'/../../app/Providers/Filament',
        ],
        StaticCallToMethodCallRector::class => [
            // Service providers use boot()/register(), not constructor injection.
            __DIR__.'/../../app/Providers',
            // Application and Factory are not serializable — injecting them would break queue chunk jobs.
            __DIR__.'/../../app/Imports/Filament/SpreadsheetImporter.php',
            // Factories have a complex multi-param constructor; Hash:: is idiomatic in seeders/factories.
            __DIR__.'/../../database',
        ],
        // @see https://github.com/driftingly/rector-laravel/issues/496
        RequestStaticValidateToInjectRector::class => [
            __DIR__.'/../../app/Providers/AppServiceProvider.php',
        ],
    ])
    ->withParallel()
    ->withCache(
        __DIR__.'/../../var/cache/rector',
        FileCacheStorage::class,
    );
