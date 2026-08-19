<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeFieldGroupCommand;
use Studiometa\Foehn\Console\Commands\MakeOptionsPageCommand;

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
    }
});
