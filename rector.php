<?php

declare(strict_types=1);

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withRules([AddNameToLiteralArgumentRector::class])
    ->withSkip([
        // Removes the `@var mixed` psalm requires on an assignment from a
        // mixed-returning call. Rector reads them as useless; psalm reports
        // MixedAssignment without them. The documented rector<->psalm
        // conflict, resolved the same way as in media-converter.
        RemoveUselessVarTagRector::class,
    ]);
