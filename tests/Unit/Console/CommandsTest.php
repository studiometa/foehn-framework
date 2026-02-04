<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\DiscoveryClearCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryGenerateCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryStatusCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryWarmCommand;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeControllerCommand;
use Studiometa\Foehn\Console\Commands\MakeHooksCommand;
use Studiometa\Foehn\Console\Commands\MakePatternCommand;
use Studiometa\Foehn\Console\Commands\MakePostTypeCommand;
use Studiometa\Foehn\Console\Commands\MakeShortcodeCommand;
use Studiometa\Foehn\Console\Commands\MakeTaxonomyCommand;
use Studiometa\Foehn\Console\Commands\MakeViewComposerCommand;

describe('Commands', function (): void {
    $commands = [
        'MakePostTypeCommand' => ['class' => MakePostTypeCommand::class, 'name' => 'make:post-type'],
        'MakeTaxonomyCommand' => ['class' => MakeTaxonomyCommand::class, 'name' => 'make:taxonomy'],
        'MakeBlockCommand' => ['class' => MakeBlockCommand::class, 'name' => 'make:block'],
        'MakeAcfBlockCommand' => ['class' => MakeAcfBlockCommand::class, 'name' => 'make:acf-block'],
        'MakePatternCommand' => ['class' => MakePatternCommand::class, 'name' => 'make:pattern'],
        'MakeViewComposerCommand' => ['class' => MakeViewComposerCommand::class, 'name' => 'make:view-composer'],
        'MakeShortcodeCommand' => ['class' => MakeShortcodeCommand::class, 'name' => 'make:shortcode'],
        'MakeControllerCommand' => ['class' => MakeControllerCommand::class, 'name' => 'make:controller'],
        'MakeHooksCommand' => ['class' => MakeHooksCommand::class, 'name' => 'make:hooks'],
        'DiscoveryClearCommand' => ['class' => DiscoveryClearCommand::class, 'name' => 'discovery:clear'],
        'DiscoveryGenerateCommand' => ['class' => DiscoveryGenerateCommand::class, 'name' => 'discovery:generate'],
        'DiscoveryStatusCommand' => ['class' => DiscoveryStatusCommand::class, 'name' => 'discovery:status'],
        'DiscoveryWarmCommand' => ['class' => DiscoveryWarmCommand::class, 'name' => 'discovery:warm'],
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
