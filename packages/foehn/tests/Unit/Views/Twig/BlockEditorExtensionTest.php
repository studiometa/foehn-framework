<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Blocks\EditorCanvas;
use Studiometa\Foehn\Views\Twig\BlockEditorExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

beforeEach(function () {
    EditorCanvas::reset();

    $this->twig = new Environment(new ArrayLoader([
        'title' => '{% if title or is_block_editor() %}<h2 {{ block_editable(name) }}>{{ title }}</h2>{% endif %}',
    ]));
    $this->twig->addExtension(new BlockEditorExtension());
});

afterEach(function () {
    EditorCanvas::reset();
});

function onCanvas(callable $render): mixed
{
    $request = new WP_REST_Request('POST', '/wp/v2/block-renderer/test/hero');
    $request->set_param(EditorCanvas::PARAM, '1');
    EditorCanvas::capture(null, [], $request);

    return EditorCanvas::render(static fn(): mixed => $render());
}

describe('BlockEditorExtension', function () {
    it('is discovered, so templates can use its functions', function () {
        expect(new ReflectionClass(BlockEditorExtension::class)->getAttributes(AsTwigExtension::class))
            ->toHaveCount(1);
    });

    it('prints nothing on the site', function () {
        expect($this->twig->render('title', ['title' => 'Hello', 'name' => 'title']))->toBe('<h2 >Hello</h2>');
        expect($this->twig->render('title', ['title' => '', 'name' => 'title']))->toBe('');
    });

    it('marks the editable element on the editor canvas, even when it is empty', function () {
        $html = onCanvas(fn(): string => $this->twig->render('title', ['title' => '', 'name' => 'title']));

        expect($html)->toBe('<h2 data-foehn-editable="title"></h2>');
    });

    it('escapes the attribute name', function () {
        $html = onCanvas(fn(): string => $this->twig->render('title', ['title' => 'A', 'name' => 'a"b']));

        expect($html)->toBe('<h2 data-foehn-editable="a&quot;b">A</h2>');
    });
});
