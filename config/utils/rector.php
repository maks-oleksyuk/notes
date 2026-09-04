<?php

declare(strict_types=1);

use DrupalFinder\DrupalFinderComposerRuntime;
use DrupalRector\Set\DrupalSetProvider;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

$drupalFinder = new DrupalFinderComposerRuntime();
$drupalRoot = $drupalFinder->getDrupalRoot();

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/../../app',
  ])
  ->withFileExtensions([
    'php',
    'inc',
    'install',
    'module',
    'theme',
    'profile',
  ])
  ->withPhpVersion(PhpVersion::PHP_85)
  ->withPhpSets(php85: true)
  ->withSetProviders(DrupalSetProvider::class)
  ->withComposerBased(
    twig: true,
    drupal: true,
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
    instanceOf: true,
    earlyReturn: true,
    carbon: true,
  )
  ->withAutoloadPaths([
    $drupalRoot . '/core',
    $drupalRoot . '/modules',
    $drupalRoot . '/profiles',
    $drupalRoot . '/themes',
  ])
  ->withParallel()
  ->withCache(
    __DIR__ . '/../../var/cache/rector',
    FileCacheStorage::class,
  );
