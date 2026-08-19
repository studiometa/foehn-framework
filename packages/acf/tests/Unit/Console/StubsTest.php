<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Console\Stubs\AcfBlockStub;
use Studiometa\Foehn\Console\Stubs\FieldGroupStub;
use Studiometa\Foehn\Console\Stubs\OptionsPageStub;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Tempest\Discovery\SkipDiscovery;

/**
 * The stubs the make: commands copy from.
 *
 * Each carries real attributes, so #[SkipDiscovery] on it is load-bearing:
 * without it, scanning this package registers a dummy ACF block on every site.
 */
describe('ACF stubs', function (): void {
    it('AcfBlockStub has correct attributes and implements AcfBlockInterface', function (): void {
        $reflection = new ReflectionClass(AcfBlockStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsAcfBlock::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(AcfBlockInterface::class))
            ->toBeTrue();
    });

    it('FieldGroupStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(FieldGroupStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsAcfFieldGroup::class))
            ->toHaveCount(1);

        $attribute = $reflection->getAttributes(AsAcfFieldGroup::class)[0]->newInstance();
        expect($attribute->name)->toBe('dummy_field_group');
        expect($attribute->title)->toBe('Dummy Field Group');
    });

    it('OptionsPageStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(OptionsPageStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsAcfOptionsPage::class))
            ->toHaveCount(1);

        $attribute = $reflection->getAttributes(AsAcfOptionsPage::class)[0]->newInstance();
        expect($attribute->menuSlug)->toBe('dummy-options');
    });
});
