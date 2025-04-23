<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return new PhpCsFixer\Config()
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder(new PhpCsFixer\Finder()
        ->in(__DIR__ . '/../../config')
        ->in(__DIR__ . '/../../src')
    )
    ->setCacheFile(__DIR__ . '/../../var/cache/php-cs-fixer/.php-cs-fixer.cache');
