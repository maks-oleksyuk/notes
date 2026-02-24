<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x5Migration' => true,
        '@PHP8x5Migration:risky' => true,

        'date_time_immutable' => true,
        'mb_str_functions' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_interfaces' => true,
        'php_unit_strict' => true,
        'protected_to_private' => true,
        'self_static_accessor' => true,
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder(new PhpCsFixer\Finder()
        ->in(__DIR__ . '/../../bin')
        ->in(__DIR__ . '/../../config')
        ->in(__DIR__ . '/../../src')
        ->in(__DIR__ . '/../../tests')
        ->in(__DIR__ . '/../../translations')
    )
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/../../var/cache/php-cs-fixer/.php-cs-fixer.cache')
    ->setUsingCache(true);
