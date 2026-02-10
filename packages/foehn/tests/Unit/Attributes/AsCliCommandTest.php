<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;

describe('AsCliCommand', function (): void {
    it('can be instantiated with minimal parameters', function (): void {
        $attribute = new AsCliCommand(name: 'make:test', description: 'Test command');

        expect($attribute->name)
            ->toBe('make:test')
            ->and($attribute->description)
            ->toBe('Test command')
            ->and($attribute->longDescription)
            ->toBeNull();
    });

    it('can be instantiated with all parameters', function (): void {
        $attribute = new AsCliCommand(
            name: 'make:test',
            description: 'Test command',
            longDescription: 'This is a long description with examples.',
        );

        expect($attribute->name)
            ->toBe('make:test')
            ->and($attribute->description)
            ->toBe('Test command')
            ->and($attribute->longDescription)
            ->toBe('This is a long description with examples.');
    });

    it('is a class attribute', function (): void {
        $reflection = new ReflectionClass(AsCliCommand::class);
        $attributes = $reflection->getAttributes();

        expect($attributes)->toHaveCount(1);

        $attributeReflection = $attributes[0]->newInstance();

        expect($attributeReflection)->toBeInstanceOf(\Attribute::class);
    });

    it('is readonly', function (): void {
        $reflection = new ReflectionClass(AsCliCommand::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });
});
