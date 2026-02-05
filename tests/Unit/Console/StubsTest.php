<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Console\Stubs\AcfBlockStub;
use Studiometa\Foehn\Console\Stubs\BlockPatternStub;
use Studiometa\Foehn\Console\Stubs\BlockStub;
use Studiometa\Foehn\Console\Stubs\HooksStub;
use Studiometa\Foehn\Console\Stubs\InteractiveBlockStub;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Studiometa\Foehn\Console\Stubs\ShortcodeStub;
use Studiometa\Foehn\Console\Stubs\TaxonomyStub;
use Studiometa\Foehn\Console\Stubs\TemplateControllerStub;
use Studiometa\Foehn\Console\Stubs\ContextProviderStub;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\BlockPatternInterface;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Tempest\Discovery\SkipDiscovery;

describe('Stubs', function (): void {
    it('PostTypeStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(PostTypeStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsPostType::class))
            ->toHaveCount(1);

        $attribute = $reflection->getAttributes(AsPostType::class)[0]->newInstance();
        expect($attribute->name)->toBe('dummy-post-type');
    });

    it('TaxonomyStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(TaxonomyStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsTaxonomy::class))
            ->toHaveCount(1);

        $attribute = $reflection->getAttributes(AsTaxonomy::class)[0]->newInstance();
        expect($attribute->name)->toBe('dummy-taxonomy');
    });

    it('BlockStub has correct attributes and implements BlockInterface', function (): void {
        $reflection = new ReflectionClass(BlockStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsBlock::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(BlockInterface::class))
            ->toBeTrue();

        $attribute = $reflection->getAttributes(AsBlock::class)[0]->newInstance();
        expect($attribute->name)->toBe('theme/dummy-block');
    });

    it('InteractiveBlockStub implements InteractiveBlockInterface', function (): void {
        $reflection = new ReflectionClass(InteractiveBlockStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsBlock::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(InteractiveBlockInterface::class))
            ->toBeTrue();

        $attribute = $reflection->getAttributes(AsBlock::class)[0]->newInstance();
        expect($attribute->interactivity)->toBeTrue();
    });

    it('AcfBlockStub has correct attributes and implements AcfBlockInterface', function (): void {
        $reflection = new ReflectionClass(AcfBlockStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsAcfBlock::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(AcfBlockInterface::class))
            ->toBeTrue();
    });

    it('BlockPatternStub has correct attributes and implements BlockPatternInterface', function (): void {
        $reflection = new ReflectionClass(BlockPatternStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsBlockPattern::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(BlockPatternInterface::class))
            ->toBeTrue();
    });

    it('ContextProviderStub has correct attributes and implements ContextProviderInterface', function (): void {
        $reflection = new ReflectionClass(ContextProviderStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsContextProvider::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(ContextProviderInterface::class))
            ->toBeTrue();
    });

    it('ShortcodeStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(ShortcodeStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))->toHaveCount(1);

        // Check method has AsShortcode attribute
        $method = $reflection->getMethod('handle');
        expect($method->getAttributes(AsShortcode::class))->toHaveCount(1);
    });

    it('TemplateControllerStub has correct attributes and implements TemplateControllerInterface', function (): void {
        $reflection = new ReflectionClass(TemplateControllerStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsTemplateController::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(TemplateControllerInterface::class))
            ->toBeTrue();

        $attribute = $reflection->getAttributes(AsTemplateController::class)[0]->newInstance();
        expect($attribute->templates)->toBe('dummy-template');
    });

    it('HooksStub has correct attributes and hook methods', function (): void {
        $reflection = new ReflectionClass(HooksStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))->toHaveCount(1);

        // Check methods have hook attributes
        $initMethod = $reflection->getMethod('onInit');
        expect($initMethod->getAttributes(AsAction::class))->toHaveCount(1);

        $filterMethod = $reflection->getMethod('filterTitle');
        expect($filterMethod->getAttributes(AsFilter::class))->toHaveCount(1);
    });
});
