<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeFieldGroupCommand;
use Studiometa\Foehn\Console\Commands\MakeOptionsPageCommand;
use Studiometa\Foehn\Console\ProductionGuard;
use Studiometa\Foehn\Console\WpCli;

describe('ACF commands', function (): void {
    $commands = [
        'MakeAcfBlockCommand' => ['class' => MakeAcfBlockCommand::class, 'name' => 'make:acf-block'],
        'MakeFieldGroupCommand' => ['class' => MakeFieldGroupCommand::class, 'name' => 'make:field-group'],
        'MakeOptionsPageCommand' => ['class' => MakeOptionsPageCommand::class, 'name' => 'make:options-page'],
    ];

    foreach ($commands as $label => $data) {
        it("{$label} implements CliCommandInterface", function () use ($data): void {
            expect(new ReflectionClass($data['class'])->implementsInterface(CliCommandInterface::class))->toBeTrue();
        });

        it("{$label} has AsCliCommand attribute with correct name", function () use ($data): void {
            $attributes = new ReflectionClass($data['class'])->getAttributes(AsCliCommand::class);

            expect($attributes)->toHaveCount(1);

            $attribute = $attributes[0]->newInstance();

            expect($attribute->name)->toBe($data['name'])->and($attribute->description)->not->toBeEmpty();
        });

        it("{$label} does not run in production without --force or --dry-run", function () use ($data): void {
            wp_stub_reset();
            $GLOBALS['wp_stub_environment_type'] = 'production';
            $attribute = new ReflectionClass($data['class'])->getAttributes(AsCliCommand::class)[0]->newInstance();
            $guard = new ProductionGuard(new WpCli());

            try {
                expect($attribute->writes)->toBeTrue();
                expect($attribute->longDescription)->toContain('[--force]')->toContain('[--dry-run]');
                $command = "foehn-acf {$data['name']}";
                expect($guard->allows($command, $attribute, []))->toBeFalse();
                expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])
                    ->toStartWith("`wp foehn-acf {$data['name']}` changes content or files");
                expect($guard->allows($command, $attribute, ['force' => true]))->toBeTrue();
                expect($guard->allows($command, $attribute, ['dry-run' => true]))->toBeTrue();
            } finally {
                unset($GLOBALS['wp_stub_environment_type']);
            }
        });
    }
});
