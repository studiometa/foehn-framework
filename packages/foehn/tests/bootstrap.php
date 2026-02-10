<?php

declare(strict_types=1);

// Load WordPress function stubs before autoload so they're available
// when discovery classes are loaded.
require_once __DIR__ . '/wp-stubs.php';

// Load Composer autoloader
// In monorepo: vendor/ is at the repository root
// In standalone: vendor/ is at the package root
$monorepoAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
$standaloneAutoload = dirname(__DIR__) . '/vendor/autoload.php';
require_once file_exists($monorepoAutoload) ? $monorepoAutoload : $standaloneAutoload;
