<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\DiscoveryClearCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryGenerateCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryListCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryStatusCommand;
use Studiometa\Foehn\Console\Commands\MakeBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeContextCommand;
use Studiometa\Foehn\Console\Commands\MakeContextProviderCommand;
use Studiometa\Foehn\Console\Commands\MakeControllerCommand;
use Studiometa\Foehn\Console\Commands\MakeHooksCommand;
use Studiometa\Foehn\Console\Commands\MakeImageSizeCommand;
use Studiometa\Foehn\Console\Commands\MakeMenuCommand;
use Studiometa\Foehn\Console\Commands\MakeModelCommand;
use Studiometa\Foehn\Console\Commands\MakePatternCommand;
use Studiometa\Foehn\Console\Commands\MakePostTypeCommand;
use Studiometa\Foehn\Console\Commands\MakeShortcodeCommand;
use Studiometa\Foehn\Console\Commands\MakeTaxonomyCommand;
use Studiometa\Foehn\Console\Commands\PageCacheClearCommand;
use Studiometa\Foehn\Console\Commands\PageCacheConfigCommand;
use Studiometa\Foehn\Console\Commands\PageCacheStatusCommand;
use Studiometa\Foehn\Console\Commands\PageCacheWarmCommand;
use Studiometa\Foehn\Console\Commands\RewriteFlushCommand;
use Studiometa\Foehn\Verification\VerifyCommand;

describe('Commands', function (): void {
    $commands = [
        'MakePostTypeCommand' => ['class' => MakePostTypeCommand::class, 'name' => 'make:post-type'],
        'MakeTaxonomyCommand' => ['class' => MakeTaxonomyCommand::class, 'name' => 'make:taxonomy'],
        'MakeBlockCommand' => ['class' => MakeBlockCommand::class, 'name' => 'make:block'],
        'MakePatternCommand' => ['class' => MakePatternCommand::class, 'name' => 'make:pattern'],
        'MakeContextProviderCommand' => [
            'class' => MakeContextProviderCommand::class,
            'name' => 'make:context-provider',
        ],
        'MakeShortcodeCommand' => ['class' => MakeShortcodeCommand::class, 'name' => 'make:shortcode'],
        'MakeControllerCommand' => ['class' => MakeControllerCommand::class, 'name' => 'make:controller'],
        'MakeHooksCommand' => ['class' => MakeHooksCommand::class, 'name' => 'make:hooks'],
        'MakeModelCommand' => ['class' => MakeModelCommand::class, 'name' => 'make:model'],
        'MakeContextCommand' => ['class' => MakeContextCommand::class, 'name' => 'make:context'],
        'MakeMenuCommand' => ['class' => MakeMenuCommand::class, 'name' => 'make:menu'],
        'MakeImageSizeCommand' => ['class' => MakeImageSizeCommand::class, 'name' => 'make:image-size'],
        'DiscoveryClearCommand' => ['class' => DiscoveryClearCommand::class, 'name' => 'discovery:clear'],
        'DiscoveryGenerateCommand' => ['class' => DiscoveryGenerateCommand::class, 'name' => 'discovery:generate'],
        'DiscoveryStatusCommand' => ['class' => DiscoveryStatusCommand::class, 'name' => 'discovery:status'],
        'DiscoveryListCommand' => ['class' => DiscoveryListCommand::class, 'name' => 'discovery:list'],
        'RewriteFlushCommand' => ['class' => RewriteFlushCommand::class, 'name' => 'rewrite:flush'],
        'PageCacheClearCommand' => ['class' => PageCacheClearCommand::class, 'name' => 'cache:clear'],
        'PageCacheStatusCommand' => ['class' => PageCacheStatusCommand::class, 'name' => 'cache:status'],
        'PageCacheConfigCommand' => ['class' => PageCacheConfigCommand::class, 'name' => 'cache:config'],
        'PageCacheWarmCommand' => ['class' => PageCacheWarmCommand::class, 'name' => 'cache:warm'],
        'VerifyCommand' => ['class' => VerifyCommand::class, 'name' => 'verify'],
    ];

    foreach ($commands as $label => $data) {
        it("{$label} implements CliCommandInterface", function () use ($data): void {
            $reflection = new ReflectionClass($data['class']);

            expect($reflection->implementsInterface(CliCommandInterface::class))->toBeTrue();
        });

        it("{$label} has AsCliCommand attribute with correct name", function () use ($data): void {
            $reflection = new ReflectionClass($data['class']);
            $attributes = $reflection->getAttributes(AsCliCommand::class);

            expect($attributes)->toHaveCount(1);

            $attribute = $attributes[0]->newInstance();

            expect($attribute->name)->toBe($data['name'])->and($attribute->description)->not->toBeEmpty();
        });
    }
});
