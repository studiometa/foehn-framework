<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\Commands\DiscoveryClearCommand;
use Studiometa\WPTempest\Console\Commands\DiscoveryGenerateCommand;
use Studiometa\WPTempest\Console\Commands\DiscoveryStatusCommand;
use Studiometa\WPTempest\Console\Commands\MakeAcfBlockCommand;
use Studiometa\WPTempest\Console\Commands\MakeBlockCommand;
use Studiometa\WPTempest\Console\Commands\MakeControllerCommand;
use Studiometa\WPTempest\Console\Commands\MakeHooksCommand;
use Studiometa\WPTempest\Console\Commands\MakePatternCommand;
use Studiometa\WPTempest\Console\Commands\MakePostTypeCommand;
use Studiometa\WPTempest\Console\Commands\MakeShortcodeCommand;
use Studiometa\WPTempest\Console\Commands\MakeTaxonomyCommand;
use Studiometa\WPTempest\Console\Commands\MakeViewComposerCommand;

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
