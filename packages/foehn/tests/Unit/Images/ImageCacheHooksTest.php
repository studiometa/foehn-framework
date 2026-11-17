<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Images\ImageCacheHooks;

/** A transformer that records which sources it was asked to forget. */
final class ForgetSpyTransformer implements ImageTransformer
{
    /** @var list<string> */
    public array $forgotten = [];

    public function url(string $src, array $transform): string
    {
        return $src;
    }

    public function forget(string $src): void
    {
        $this->forgotten[] = $src;
    }
}

describe('ImageCacheHooks', function () {
    $scaled = 'http://example.com/wp-content/uploads/2026/11/photo-scaled.jpg';
    $original = 'http://example.com/wp-content/uploads/2026/11/photo.jpg';

    beforeEach(function () {
        $GLOBALS['wp_stub_attachments'] = [];
    });

    it('forgets the attached file when the metadata is updated', function () use ($original) {
        $GLOBALS['wp_stub_attachments'][7] = ['url' => $original];
        $transformer = new ForgetSpyTransformer();

        $metadata = new ImageCacheHooks($transformer)->onUpdate(['width' => 3000], 7);

        expect($metadata)->toBe(['width' => 3000]);
        expect($transformer->forgotten)->toBe([$original]);
    });

    // A scaled upload keeps the full-size file as `original_image`. A transform
    // cached under that path must go too, or it stays in the cache forever.
    it('also forgets the full-size original of a scaled image on update', function () use ($scaled, $original) {
        $GLOBALS['wp_stub_attachments'][7] = ['url' => $scaled, 'original' => $original];
        $transformer = new ForgetSpyTransformer();

        new ImageCacheHooks($transformer)->onUpdate([], 7);

        expect($transformer->forgotten)->toBe([$scaled, $original]);
    });

    it('also forgets the full-size original of a scaled image on delete', function () use ($scaled, $original) {
        $GLOBALS['wp_stub_attachments'][7] = ['url' => $scaled, 'original' => $original];
        $transformer = new ForgetSpyTransformer();

        new ImageCacheHooks($transformer)->onDelete(7);

        expect($transformer->forgotten)->toBe([$scaled, $original]);
    });

    it('forgets nothing for an attachment it cannot resolve', function () {
        $transformer = new ForgetSpyTransformer();

        new ImageCacheHooks($transformer)->onDelete(404);

        expect($transformer->forgotten)->toBe([]);
    });
});
