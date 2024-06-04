<?php

declare(strict_types=1);

use DrupalFinder\DrupalFinderComposerRuntime;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal8SetList;
use DrupalRector\Set\Drupal9SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        Drupal8SetList::DRUPAL_8,
        Drupal9SetList::DRUPAL_9,
        Drupal10SetList::DRUPAL_10,
    ]);

    $drupalFinder = new DrupalFinderComposerRuntime();
    $drupalRoot = $drupalFinder->getDrupalRoot();
    $rectorConfig->autoloadPaths([
        $drupalRoot . '/core',
        $drupalRoot . '/modules',
        $drupalRoot . '/profiles',
        $drupalRoot . '/themes'
    ]);

    $rectorConfig->fileExtensions(['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);
    $rectorConfig->importNames(TRUE, FALSE);
    $rectorConfig->importShortClasses(FALSE);
};
