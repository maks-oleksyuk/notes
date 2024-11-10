<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/../../config')
    ->in(__DIR__ . '/../../src')
    ->exclude('var');

return (new PhpCsFixer\Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/../../var/.php-cs-fixer.cache');
