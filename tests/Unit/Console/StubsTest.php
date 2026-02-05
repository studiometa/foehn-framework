<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Console\Stubs\AcfBlockStub;
use Studiometa\Foehn\Console\Stubs\BlockPatternStub;
use Studiometa\Foehn\Console\Stubs\BlockStub;
use Studiometa\Foehn\Console\Stubs\ContextProviderStub;
use Studiometa\Foehn\Console\Stubs\FieldGroupStub;
use Studiometa\Foehn\Console\Stubs\HooksStub;
use Studiometa\Foehn\Console\Stubs\ImageSizeStub;
use Studiometa\Foehn\Console\Stubs\InteractiveBlockStub;
use Studiometa\Foehn\Console\Stubs\MenuStub;
use Studiometa\Foehn\Console\Stubs\ModelStub;
use Studiometa\Foehn\Console\Stubs\OptionsPageStub;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Studiometa\Foehn\Console\Stubs\ShortcodeStub;
use Studiometa\Foehn\Console\Stubs\TaxonomyStub;
use Studiometa\Foehn\Console\Stubs\TemplateControllerStub;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\BlockPatternInterface;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
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

    it('ModelStub has correct attributes and extends Timber Post', function (): void {
        $reflection = new ReflectionClass(ModelStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))->toHaveCount(1);
        expect($reflection->getParentClass()->getName())->toBe('Timber\\Post');
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

    it('MenuStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(MenuStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsMenu::class))
            ->toHaveCount(1);

        $attribute = $reflection->getAttributes(AsMenu::class)[0]->newInstance();
        expect($attribute->location)->toBe('dummy-menu');
    });

    it('ImageSizeStub has correct attributes', function (): void {
        $reflection = new ReflectionClass(ImageSizeStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsImageSize::class))
            ->toHaveCount(1);

        $attribute = $reflection->getAttributes(AsImageSize::class)[0]->newInstance();
        expect($attribute->name)->toBe('dummy-size');
        expect($attribute->width)->toBe(800);
        expect($attribute->height)->toBe(600);
        expect($attribute->crop)->toBeTrue();
    });
});
