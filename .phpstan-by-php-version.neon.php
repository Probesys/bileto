<?php

declare(strict_types = 1);

$includes = [];

if (PHP_VERSION_ID >= 80500) {
    $includes[] = __DIR__ . '/.phpstan-8.5-baseline.neon';
}

$config = [];
$config['includes'] = $includes;
$config['parameters']['phpVersion'] = PHP_VERSION_ID;

return $config;
