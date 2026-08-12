<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/lib',
        __DIR__ . '/tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_80)
    ->withPhpSets(php80: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        earlyReturn: true,
    )
    ->withImportNames(removeUnusedImports: true)
    ->withParallel()
    ->withCache(__DIR__ . '/.rector-cache')
    ->withSkip([
        TypedPropertyFromAssignsRector::class => [__DIR__ . '/tests'],
    ])
    ->withConfiguredRule(TypedPropertyFromAssignsRector::class, [TypedPropertyFromAssignsRector::INLINE_PUBLIC => true]);
