<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/routes',
    ])
    ->withPhpSets(php84: true)
    ->withRules([
        TypedPropertyFromAssignsRector::class,
    ]);
