<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockRenderer;
use Studiometa\Foehn\Blocks\EditorCanvas;
use Studiometa\Foehn\Contracts\BlockInterface;

/**
 * A block that renders the content it receives, and whether it was a canvas render.
 */
function canvasProbe(?callable $inside = null): BlockInterface
{
    return new class($inside) implements BlockInterface {
        public function __construct(
            private $inside,
        ) {}

        public static function attributes(): array
        {
            return [];
        }

        public function compose(array $attributes, string $content, WP_Block $block): array
        {
            return [];
        }

        public function render(array $attributes, string $content, WP_Block $block): string
        {
            $nested = $this->inside === null ? '' : ($this->inside)();

            return sprintf('[%s|%s%s]', EditorCanvas::isActive() ? 'canvas' : 'site', $content, $nested);
        }
    };
}

function captureCanvasRequest(string $route, mixed $flag): void
{
    $request = new WP_REST_Request('POST', $route);
    $request->set_param(EditorCanvas::PARAM, $flag);

    EditorCanvas::capture(null, [], $request);
}

beforeEach(function () {
    EditorCanvas::reset();
});

afterEach(function () {
    EditorCanvas::reset();
});

describe('EditorCanvas::capture', function () {
    it('passes the response through untouched', function () {
        $request = new WP_REST_Request('POST', '/wp/v2/block-renderer/test/hero');

        expect(EditorCanvas::capture('response', [], $request))->toBe('response');
    });

    it('is a canvas render for a flagged block-renderer request only', function (string $route, mixed $flag, bool $expected) {
        captureCanvasRequest($route, $flag);

        $active = EditorCanvas::render(static fn(bool $canvas): bool => $canvas);

        expect($active)->toBe($expected);
    })->with([
        'flagged block renderer' => ['/wp/v2/block-renderer/test/hero', '1', true],
        'block renderer without the flag' => ['/wp/v2/block-renderer/test/hero', null, false],
        'flag set to false' => ['/wp/v2/block-renderer/test/hero', 'false', false],
        'flag on another route' => ['/wp/v2/posts', '1', false],
    ]);

    it('forgets the previous request', function () {
        captureCanvasRequest('/wp/v2/block-renderer/test/hero', '1');
        captureCanvasRequest('/wp/v2/posts', null);

        expect(EditorCanvas::render(static fn(bool $canvas): bool => $canvas))->toBeFalse();
    });
});

describe('EditorCanvas::isActive', function () {
    it('is false outside a block render', function () {
        captureCanvasRequest('/wp/v2/block-renderer/test/hero', '1');

        expect(EditorCanvas::isActive())->toBeFalse();
    });
});

describe('BlockRenderer on the editor canvas', function () {
    it('hands a container the inner blocks marker instead of its saved content', function () {
        captureCanvasRequest('/wp/v2/block-renderer/test/section', '1');

        $html = new BlockRenderer()->render(canvasProbe(), [], '<p>Saved</p>', new WP_Block(), null, true);

        expect($html)->toBe('[canvas|' . EditorCanvas::INNER_BLOCKS . ']');
    });

    it('keeps the content of a block that is not a container', function () {
        captureCanvasRequest('/wp/v2/block-renderer/test/hero', '1');

        $html = new BlockRenderer()->render(canvasProbe(), [], 'Inner', new WP_Block());

        expect($html)->toBe('[canvas|Inner]');
    });

    it('renders as on the site outside a canvas request', function () {
        $html = new BlockRenderer()->render(canvasProbe(), [], '<p>Saved</p>', new WP_Block(), null, true);

        expect($html)->toBe('[site|<p>Saved</p>]');
    });

    it('renders a block nested in the canvas block as on the site', function () {
        captureCanvasRequest('/wp/v2/block-renderer/test/section', '1');

        $renderer = new BlockRenderer();
        $nested = static fn(): string => $renderer->render(canvasProbe(), [], '<p>Nested</p>', new WP_Block(), null, true);

        $html = $renderer->render(canvasProbe($nested), [], '', new WP_Block(), null, true);

        expect($html)->toBe('[canvas|' . EditorCanvas::INNER_BLOCKS . '[site|<p>Nested</p>]]');
    });

    it('leaves canvas mode when a render throws', function () {
        captureCanvasRequest('/wp/v2/block-renderer/test/hero', '1');

        try {
            EditorCanvas::render(static fn(): never => throw new RuntimeException('Boom'));
        } catch (RuntimeException) {
        }

        expect(EditorCanvas::render(static fn(bool $canvas): bool => $canvas))->toBeTrue();
    });
});
