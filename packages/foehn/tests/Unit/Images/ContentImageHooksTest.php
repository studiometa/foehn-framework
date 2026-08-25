<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Images\ContentImageHooks;
use Studiometa\Foehn\Images\NullTransformer;

/** A transformer that answers any width, so the ladder can be observed whole. */
final class SpyTransformer implements ImageTransformer
{
    public function url(string $src, array $transform): string
    {
        return $src . '?w=' . ($transform['w'] ?? '');
    }

    public function forget(string $src): void {}
}

/**
 * A transformer bounded like the real one: past its ceiling it hands the URL
 * back, which is how `GlideTransformer` refuses a transform it will not serve.
 */
final class BoundedTransformer implements ImageTransformer
{
    public function __construct(
        private int $max,
    ) {}

    public function url(string $src, array $transform): string
    {
        $width = (int) ($transform['w'] ?? 0);

        return $width > $this->max ? $src : $src . '?w=' . $width;
    }

    public function forget(string $src): void {}
}

/**
 * @param int|null $width The original's width, or null for an attachment whose
 *                        metadata carries none — which is ordinary.
 */
function contentImageAttachment(int $id, string $url, ?int $width = null): void
{
    $GLOBALS['wp_stub_attachments'][$id] = [
        'url' => $url,
        'meta' => $width === null ? false : ['width' => $width, 'height' => 100],
    ];
}

describe('ContentImageHooks', function () {
    $source = 'http://example.com/wp-content/uploads/a/photo.jpg';

    beforeEach(function () {
        $GLOBALS['wp_stub_attachments'] = [];
    });

    it('leaves an image that is not in the library alone', function () {
        expect(new ContentImageHooks(new SpyTransformer())->attributes(0))->toBeNull();
    });

    it('leaves an attachment it cannot resolve alone', function () {
        expect(new ContentImageHooks(new SpyTransformer())->attributes(404))->toBeNull();
    });

    // With nothing configured every candidate would be the same file under a
    // different width, which is worse than leaving the tag as it was.
    it('is inert with no transformer configured', function () use ($source) {
        contentImageAttachment(7, $source, 3000);

        expect(new ContentImageHooks(new NullTransformer())->attributes(7))->toBeNull();
    });

    // The whole point: every candidate comes from the original, so `src` stops
    // naming an intermediate file — the one that may not exist.
    it('derives every candidate from the original', function () use ($source) {
        contentImageAttachment(7, $source, 3000);

        $attributes = new ContentImageHooks(new SpyTransformer())->attributes(7);

        expect($attributes['src'])->toStartWith($source . '?w=');
        expect($attributes['srcset'])->toContain($source . '?w=400 400w');
        expect($attributes['srcset'])->not->toContain('-514x570');
    });

    // An upscale is bytes spent on pixels that were never photographed.
    it('never offers a width past the original', function () use ($source) {
        contentImageAttachment(7, $source, 900);

        $attributes = new ContentImageHooks(new SpyTransformer())->attributes(7);

        expect($attributes['srcset'])->toContain('400w')->toContain('800w');
        expect($attributes['srcset'])->not->toContain('1200w');
        expect($attributes['sizes'])->toBe('(max-width: 800px) 100vw, 800px');
    });

    // An image narrower than the smallest rung still goes through the
    // transformer, as one file at its own width.
    it('keeps a small image as its own single candidate', function () use ($source) {
        contentImageAttachment(7, $source, 320);

        $attributes = new ContentImageHooks(new SpyTransformer())->attributes(7);

        expect($attributes['srcset'])->toBe($source . '?w=320 320w');
    });

    // A candidate is only honest if the transformer will produce it. One that
    // hands the URL back describes the original as a width it is not.
    it('drops a width the transformer will not produce', function () use ($source) {
        contentImageAttachment(7, $source, 4000);

        $attributes = new ContentImageHooks(new BoundedTransformer(1200))->attributes(7);

        expect($attributes['srcset'])->toContain('800w')->toContain('1200w');
        expect($attributes['srcset'])->not->toContain('1600w')->not->toContain('2400w');
        expect($attributes['src'])->toBe($source . '?w=1200');
    });

    // Metadata is absent more often than it looks, and is not a reason to
    // refuse to size an image: the transformer's own bounds are what stop it.
    it('still offers the ladder when the metadata has no width', function () use ($source) {
        contentImageAttachment(7, $source);

        $attributes = new ContentImageHooks(new SpyTransformer())->attributes(7);

        expect($attributes['srcset'])->toContain('400w')->toContain('2400w');
    });

    // `src` is what a browser without `srcset`, a crawler and a mail client all
    // read, so it has to be the largest thing actually on offer.
    it('points src at the widest candidate', function () use ($source) {
        contentImageAttachment(7, $source, 3000);

        $attributes = new ContentImageHooks(new SpyTransformer())->attributes(7);

        expect($attributes['src'])->toBe($source . '?w=2400');
    });

    // The ladder sits on the 100px grid `GlideConfig` snaps to, so a request
    // lands on a cache entry rather than beside one.
    it('offers widths that are on the transformer grid, ascending', function () {
        $sorted = ContentImageHooks::WIDTHS;
        sort($sorted);

        expect(ContentImageHooks::WIDTHS)->toBe($sorted);

        foreach (ContentImageHooks::WIDTHS as $width) {
            expect($width % 100)->toBe(0);
        }
    });
});
