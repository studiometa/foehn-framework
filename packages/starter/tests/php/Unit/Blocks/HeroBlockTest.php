<?php

declare(strict_types=1);

use App\Blocks\HeroBlock;
use App\Data\HeroContext;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;

describe('HeroBlock', function () {
    it('implements AcfBlockInterface', function () {
        expect(is_subclass_of(HeroBlock::class, AcfBlockInterface::class))->toBeTrue();
    });

    it('has AsAcfBlock attribute with correct config', function () {
        $ref = new ReflectionClass(HeroBlock::class);
        $attrs = $ref->getAttributes(AsAcfBlock::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('hero');
        expect($attr->title)->toBe('Hero Banner');
        expect($attr->category)->toBe('layout');
        expect($attr->icon)->toBe('cover-image');
        expect($attr->keywords)->toContain('hero', 'banner', 'header');
    });

    it('fields() returns a FieldsBuilder with expected fields', function () {
        $builder = HeroBlock::fields();
        $config = $builder->build();

        $fieldNames = array_column($config['fields'], 'name');

        expect($fieldNames)->toContain('title');
        expect($fieldNames)->toContain('subtitle');
        expect($fieldNames)->toContain('background');
        expect($fieldNames)->toContain('height');
    });

    it('compose() returns a HeroContext DTO', function () {
        $view = createFakeViewEngine();
        $block = new HeroBlock($view);

        $result = $block->compose(['id' => 'block_1'], ['title' => 'Hello', 'subtitle' => 'World', 'height' => 'full']);

        expect($result)->toBeInstanceOf(HeroContext::class);
        expect($result->title)->toBe('Hello');
        expect($result->subtitle)->toBe('World');
        expect($result->height)->toBe('full');
    });

    it('render() delegates to ViewEngine', function () {
        $rendered = '';
        $view = createFakeViewEngine(renderCallback: function (string $template) use (&$rendered) {
            $rendered = $template;

            return '<div>hero</div>';
        });

        $block = new HeroBlock($view);
        $output = $block->render(['title' => 'Test']);

        expect($output)->toBe('<div>hero</div>');
        expect($rendered)->toBe('blocks/hero');
    });
});
