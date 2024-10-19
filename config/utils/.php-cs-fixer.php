<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();
$config->setParallelConfig(ParallelConfigFactory::detect());
$config->getFinder()->in(__DIR__ . '/../../ext');
$config->getFinder()->in(__DIR__ . '/../../config');
$config->setRules([
    'binary_operator_spaces' => [
        'default' => 'single_space',
    ],
]);
return $config;
