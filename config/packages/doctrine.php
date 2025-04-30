<?php

declare(strict_types=1);

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig;
use Symfony\Config\DoctrineConfig;
use Symfony\Config\FrameworkConfig;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (
    ContainerConfigurator $containerConfigurator,
    DoctrineConfig $doctrineConfig,
    FrameworkConfig $frameworkConfig,
): void {
    $dbalConfig = $doctrineConfig->dbal();
    $ormConfig = $doctrineConfig->orm();
    $entityManagerConfig = $ormConfig->entityManager('default');

    $dbalConfig
        ->connection('default')
        ->url(env('DATABASE_URL')->resolve())
        ->profilingCollectBacktrace(param('kernel.debug'))
        ->useSavepoints(true);

    $ormConfig
        ->autoGenerateProxyClasses(true)
        ->enableLazyGhostObjects(true)
        ->controllerResolver()
        ->autoMapping(false);

    $entityManagerConfig
        ->reportFieldsWhereDeclared(true)
        ->validateXmlMapping(true)
        ->namingStrategy('doctrine.orm.naming_strategy.underscore_number_aware')
        ->identityGenerationPreference(PostgreSQLPlatform::class, 'identity')
        ->autoMapping(true);

    /** @var MappingConfig $appMapping */
    $appMapping = $entityManagerConfig->mapping('App');
    $appMapping
        ->type('attribute')
        ->isBundle(false)
        ->dir(param('kernel.project_dir').'/src/Entity')
        ->prefix('App\Entity')
        ->alias('App');

    switch ($containerConfigurator->env()) {
        case 'test':
            $dbalConfig
                ->connection('default')
                ->dbnameSuffix('_test'.env('TEST_TOKEN')->default(''));

            break;
        case 'prod':
            $ormConfig
                ->autoGenerateProxyClasses(false)
                ->proxyDir(param('kernel.project_dir').'/doctrine/orm/Proxies');

            $entityManagerConfig->queryCacheDriver([
                'type' => 'pool',
                'pool' => 'doctrine.system_cache_pool',
            ]);
            $entityManagerConfig->resultCacheDriver([
                'type' => 'pool',
                'pool' => 'doctrine.result_cache_pool',
            ]);

            $cache = $frameworkConfig->cache();
            $cache->pool('doctrine.system_cache_pool')->adapters(['cache.system']);
            $cache->pool('doctrine.result_cache_pool')->adapters(['cache.app']);

            break;
    }
};
