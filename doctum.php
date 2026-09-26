<?php
// Doctum の設定。todo phpdoc (php tools/doctum.phar update doctum.php) で build/doctum/index.html を作る

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()->files()->name('*.php')->in(__DIR__ . '/app');

return new Doctum($iterator, [
    'title' => 'ChreeID',
    'build_dir' => __DIR__ . '/build/doctum',
    'cache_dir' => __DIR__ . '/build/doctum-cache',
    'default_opened_level' => 2,
]);
