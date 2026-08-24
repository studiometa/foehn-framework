<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Images\GlideConfig;
use Studiometa\Foehn\Images\GlideTransformer;
use Studiometa\Foehn\Images\NullTransformer;

describe('NullTransformer', function () {
    // The default changes nothing: a template written against the interface renders
    // correctly on a project that transforms no images at all.
    it('returns the URL untouched', function () {
        expect(new NullTransformer()->url('http://example.com/a.jpg', ['w' => 400]))->toBe('http://example.com/a.jpg');
    });

    it('implements the contract', function () {
        expect(new NullTransformer())->toBeInstanceOf(ImageTransformer::class);
    });
});

describe('GlideTransformer', function () {
    $media = 'http://example.com/wp-content/uploads/2016/06/photo.jpg';

    it('signs the URL it produces', function () use ($media) {
        $url = new GlideTransformer(new GlideConfig())->url($media, ['w' => 400, 'h' => 267, 'fit' => 'crop']);

        expect($url)->toContain('/_image/2016/06/photo.jpg');
        expect($url)->toContain('w=400');
        // Unsigned, `?w=9999` is an invitation to spend CPU.
        expect($url)->toContain('s=');
    });

    // The signature covers the parameters, so two URLs asking for the same thing
    // have to produce the same string — otherwise the same crop is cached twice,
    // under two signatures.
    it('produces the same URL whatever the parameter order', function () use ($media) {
        $transformer = new GlideTransformer(new GlideConfig());

        expect($transformer->url($media, ['w' => 400, 'h' => 267]))
            ->toBe($transformer->url($media, ['h' => 267, 'w' => 400]));
    });

    it('lets an image from outside this site\'s media through', function () {
        $ailleurs = 'https://ailleurs.test/photo.jpg';

        expect(new GlideTransformer(new GlideConfig())->url($ailleurs, ['w' => 400]))->toBe($ailleurs);
    });

    it('lets a URL with no transform asked for through', function () use ($media) {
        expect(new GlideTransformer(new GlideConfig())->url($media, []))->toBe($media);
    });

    // A `..` in a path that reaches the filesystem is how a transformer becomes a
    // way to read what it was never pointed at.
    it('refuses a path that climbs out of the media', function () {
        $remonte = 'http://example.com/wp-content/uploads/../../wp-config.php';

        expect(new GlideTransformer(new GlideConfig())->url($remonte, ['w' => 10]))->toBe($remonte);
    });
});

describe('GlideConfig::cachePath', function () {
    $media = 'http://example.com/wp-content/uploads/2016/06/photo.jpg';

    // This is the invariant the webserver rule stands on. nginx assembles the
    // cache path out of the request — the path from the URL, the signature from
    // `$arg_s` — so if PHP ever wrote it anywhere else, every hit would silently
    // miss and boot WordPress, and the site would look fine while doing it.
    it('keys a result under the same signature the URL carries', function () use ($media) {
        $url = new GlideTransformer(new GlideConfig())->url($media, ['w' => 400, 'fm' => 'webp']);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        expect(GlideConfig::cachePath('2016/06/photo.jpg', $params))->toBe('2016/06/photo.jpg/' . $params['s']);
    });

    // Called directly rather than through the route, there is no signature to key
    // on — but the path still has to be stable, and still has to separate two
    // different transforms of the same image.
    it('falls back to hashing the parameters when there is no signature', function () {
        $sans = GlideConfig::cachePath('a.jpg', ['w' => 400]);

        expect($sans)
            ->toBe(GlideConfig::cachePath('a.jpg', ['w' => 400]))
            ->and($sans)
            ->not
            ->toBe(GlideConfig::cachePath('a.jpg', ['w' => 800]))
            ->and($sans)
            ->toStartWith('a.jpg/');
    });

    // A signature is a path component here, so anything that is not one is not
    // treated as one. Otherwise `?s=../../..` picks the directory to write into.
    it('refuses to key on anything that is not a signature', function () {
        expect(GlideConfig::cachePath('a.jpg', ['s' => '../../etc/passwd', 'w' => 400]))->not->toContain('..');
    });

    // Asking the Server rather than the static method, because the wiring between
    // them is the fragile part: Glide rebinds the callable onto itself, and
    // `Closure::bind()` returns null for a static closure or one made from a
    // method. Glide reads that as "Invalid cache path callable" and every image
    // 404s — with nothing else in this file noticing, since none of it builds a
    // Server. A linter that adds `static` here is enough to cause it.
    it('is the path the Glide server actually uses', function () {
        $uploads = sys_get_temp_dir() . '/foehn-glide-' . getmypid();
        @mkdir($uploads . '/2016/06', 0o777, true);
        file_put_contents($uploads . '/2016/06/photo.jpg', 'not really a jpeg');

        $GLOBALS['wp_stub_upload_basedir'] = $uploads;

        try {
            $params = ['w' => '400', 's' => str_repeat('a', 32)];

            expect(
                new GlideConfig()
                    ->server()
                    ->getCachePath('2016/06/photo.jpg', $params),
            )
                ->toBe(GlideConfig::cachePath('2016/06/photo.jpg', $params));
        } finally {
            unset($GLOBALS['wp_stub_upload_basedir']);
            // Recursively, because building the Server creates the cache directory
            // as a side effect — and `rmdir` on a directory that is not empty is a
            // warning, which this suite fails on.
            exec('rm -rf ' . escapeshellarg($uploads));
        }
    });
});
