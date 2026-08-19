<?php

declare(strict_types=1);

use Demo\Bindings\ReadingTime;
use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;

beforeEach(function () {
    wp_stub_reset();

    $this->binding = new ReadingTime();
    $this->block = fn(?int $postId): WP_Block => new WP_Block(
        [],
        'core/paragraph',
        [],
        $postId === null ? [] : ['postId' => $postId],
    );
});

describe('ReadingTime', function () {
    it('implements BlockBindingInterface', function () {
        expect(is_subclass_of(ReadingTime::class, BlockBindingInterface::class))->toBeTrue();
    });

    it('is namespaced, as WordPress requires', function () {
        $attr = new ReflectionClass(ReadingTime::class)->getAttributes(AsBlockBinding::class)[0]->newInstance();

        expect($attr->name)->toBe('theme/reading-time');
        expect($attr->label)->toBe('Reading time');
    });

    it('asks for the post it is reading', function () {
        // WordPress passes nothing a source did not declare, so without this the
        // binding has no post to measure.
        $attr = new ReflectionClass(ReadingTime::class)->getAttributes(AsBlockBinding::class)[0]->newInstance();

        expect($attr->usesContext)->toBe(['postId']);
    });

    it('estimates the reading time of the post', function () {
        $GLOBALS['wp_stub_post_fields'][7]['post_content'] = str_repeat('word ', 400);

        expect($this->binding->value([], ($this->block)(7), 'content'))->toBe('2 minutes read');
    });

    it('rounds a short post up to a minute rather than to nothing', function () {
        $GLOBALS['wp_stub_post_fields'][7]['post_content'] = 'Three words here';

        expect($this->binding->value([], ($this->block)(7), 'content'))->toBe('1 minute read');
    });

    it('ignores the markup around the words', function () {
        $GLOBALS['wp_stub_post_fields'][7]['post_content'] = '<p>' . str_repeat('<em>word</em> ', 200) . '</p>';

        expect($this->binding->value([], ($this->block)(7), 'content'))->toBe('1 minute read');
    });

    it('leaves the attribute alone when there is no post', function () {
        // Returning null is what tells WordPress to keep what the block author
        // wrote, rather than replacing it with nothing.
        expect($this->binding->value([], ($this->block)(null), 'content'))->toBeNull();
    });

    it('leaves the attribute alone for an empty post', function () {
        $GLOBALS['wp_stub_post_fields'][7]['post_content'] = '';

        expect($this->binding->value([], ($this->block)(7), 'content'))->toBeNull();
    });
});
