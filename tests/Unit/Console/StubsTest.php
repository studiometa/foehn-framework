<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use ReflectionClass;
use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Attributes\AsBlockPattern;
use Studiometa\WPTempest\Attributes\AsFilter;
use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Attributes\AsShortcode;
use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Studiometa\WPTempest\Attributes\AsTemplateController;
use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Console\Stubs\AcfBlockStub;
use Studiometa\WPTempest\Console\Stubs\BlockPatternStub;
use Studiometa\WPTempest\Console\Stubs\BlockStub;
use Studiometa\WPTempest\Console\Stubs\HooksStub;
use Studiometa\WPTempest\Console\Stubs\InteractiveBlockStub;
use Studiometa\WPTempest\Console\Stubs\PostTypeStub;
use Studiometa\WPTempest\Console\Stubs\ShortcodeStub;
use Studiometa\WPTempest\Console\Stubs\TaxonomyStub;
use Studiometa\WPTempest\Console\Stubs\TemplateControllerStub;
use Studiometa\WPTempest\Console\Stubs\ViewComposerStub;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Studiometa\WPTempest\Contracts\BlockInterface;
use Studiometa\WPTempest\Contracts\BlockPatternInterface;
use Studiometa\WPTempest\Contracts\InteractiveBlockInterface;
use Studiometa\WPTempest\Contracts\TemplateControllerInterface;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;
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

    it('ViewComposerStub has correct attributes and implements ViewComposerInterface', function (): void {
        $reflection = new ReflectionClass(ViewComposerStub::class);

        expect($reflection->getAttributes(SkipDiscovery::class))
            ->toHaveCount(1)
            ->and($reflection->getAttributes(AsViewComposer::class))
            ->toHaveCount(1)
            ->and($reflection->implementsInterface(ViewComposerInterface::class))
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
