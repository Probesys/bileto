<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Symfony34\Rector\Closure\ContainerGetNameToTypeInTestsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/bin',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withSets([
        SetList::PHP_82,
        SetList::PHP_83,
        SetList::PHP_84,
        SetList::TYPE_DECLARATION,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withAttributesSets()
    ->withSkip([
        // This conflicts with the HTMLSanitizer component which requires named services.
        ContainerGetNameToTypeInTestsRector::class,
    ]);
