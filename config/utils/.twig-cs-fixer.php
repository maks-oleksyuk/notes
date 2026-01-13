<?php

declare(strict_types=1);

use Drupal\Core\Template\TwigTransTokenParser;
use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;

return new Config()
  ->setFinder(new Finder()
    ->in('app')
    ->exclude('tests'),
  )
  ->setCacheFile('var/cache/twig-cs-fixer.cache')
  ->addTokenParser(new TwigTransTokenParser());
