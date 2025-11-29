<?php

declare(strict_types=1);

use DrupalFinder\DrupalFinderComposerRuntime;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal8SetList;
use DrupalRector\Set\Drupal9SetList;
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
  ->withPhpVersion(PhpVersion::PHP_84)
  ->withPhpSets(php84: true)
  ->withComposerBased(
    twig: true,
  )
  ->withImportNames(
    importShortClasses: false,
    removeUnusedImports: true,
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
  ->withSets([
    Drupal8SetList::DRUPAL_8,
    Drupal9SetList::DRUPAL_9,
    Drupal10SetList::DRUPAL_10,
  ])
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
