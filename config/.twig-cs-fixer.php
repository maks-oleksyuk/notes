<?php

declare(strict_types=1);

use Drupal\Core\Template\TwigTransTokenParser;
use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;

$finder = new Finder();
$finder->exclude('tests');

$config = new Config();
$config->setFinder($finder);
$config->setCacheFile(NULL);
$config->addTokenParser(new TwigTransTokenParser());

return $config;
