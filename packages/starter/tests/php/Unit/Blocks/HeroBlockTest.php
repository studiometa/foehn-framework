<?php

declare(strict_types=1);

use App\Blocks\HeroBlock;
use App\Data\HeroContext;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Data\LinkData;

describe('HeroBlock', function () {
    it('implements BlockInterface', function () {
        expect(is_subclass_of(HeroBlock::class, BlockInterface::class))->toBeTrue();
    });

    it('has AsBlock attribute with correct config', function () {
        $attrs = new ReflectionClass(HeroBlock::class)->getAttributes(AsBlock::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        // Namespaced, unlike the ACF block it replaces: WordPress requires the
        // slash for a block registered through register_block_type().
        expect($attr->name)->toBe('theme/hero');
        expect($attr->title)->toBe('Hero Banner');
        expect($attr->category)->toBe('layout');
        expect($attr->icon)->toBe('cover-image');
        expect($attr->keywords)->toContain('hero', 'banner', 'header');
    });

    it('declares the controls the sidebar is derived from', function () {
        $attributes = HeroBlock::attributes();

        expect(array_keys($attributes))->toContain('title', 'subtitle', 'backgroundId', 'height');
        expect($attributes['height']['options'])->toHaveKeys(['auto', 'small', 'medium', 'full']);
        expect($attributes['height']['default'])->toBe('medium');
        expect($attributes['subtitle']['control'])->toBe('textarea');
        expect($attributes['backgroundId']['control'])->toBe('image');
    });

    it('compose() returns a HeroContext DTO', function () {
        $block = new HeroBlock(createFakeViewEngine());

        $result = $block->compose(['title' => 'Hello', 'subtitle' => 'World', 'height' => 'full'], '', new WP_Block());

        expect($result)->toBeInstanceOf(HeroContext::class);
        expect($result->title)->toBe('Hello');
        expect($result->subtitle)->toBe('World');
        expect($result->height)->toBe('full');
    });

    it('composes a call to action from the three attributes that replace the link field', function () {
        $block = new HeroBlock(createFakeViewEngine());

        $result = $block->compose(
            ['ctaUrl' => 'https://example.com', 'ctaLabel' => 'Read more', 'ctaTarget' => '_blank'],
            '',
            new WP_Block(),
        );

        expect($result->cta)->toBeInstanceOf(LinkData::class);
        expect($result->cta->url)->toBe('https://example.com');
        expect($result->cta->title)->toBe('Read more');
        expect($result->cta->target)->toBe('_blank');
    });

    it('composes no call to action without a URL', function () {
        $block = new HeroBlock(createFakeViewEngine());

        // A label with nowhere to go is not a button, and the template checks
        // for the object rather than for each of its parts.
        $result = $block->compose(['ctaLabel' => 'Read more'], '', new WP_Block());

        expect($result->cta)->toBeNull();
    });

    it('falls back to sensible defaults', function () {
        $result = new HeroBlock(createFakeViewEngine())->compose([], '', new WP_Block());

        expect($result->title)->toBe('');
        expect($result->subtitle)->toBeNull();
        expect($result->height)->toBe('medium');
    });

    it('treats an empty subtitle as no subtitle', function () {
        // The DTO's type says the subtitle is optional, so an empty string is
        // not a subtitle. The template checks the value, not its length.
        $result = new HeroBlock(createFakeViewEngine())->compose(['subtitle' => ''], '', new WP_Block());

        expect($result->subtitle)->toBeNull();
    });

    it('render() delegates to ViewEngine', function () {
        $rendered = '';
        $view = createFakeViewEngine(renderCallback: function (string $template) use (&$rendered) {
            $rendered = $template;

            return '<div>hero</div>';
        });

        $output = new HeroBlock($view)->render(['title' => 'Test'], '', new WP_Block());

        expect($output)->toBe('<div>hero</div>');
        expect($rendered)->toBe('blocks/hero');
    });
});
