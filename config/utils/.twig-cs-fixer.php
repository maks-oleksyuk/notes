<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;

return new Config()
    ->setFinder(new Finder()
        ->in(__DIR__ . '/../../templates')
    )
    ->setCacheFile(__DIR__ . '/../../var/cache/twig-cs-fixer/.twig-cs-fixer.cache');
