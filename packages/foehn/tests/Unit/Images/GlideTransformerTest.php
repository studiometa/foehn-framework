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

    it('builds a URL for the route', function () use ($media) {
        $url = new GlideTransformer(new GlideConfig())->url($media, ['w' => 600, 'h' => 400, 'fit' => 'crop']);

        expect($url)->toContain('/_image/2016/06/photo.jpg')->toContain('w=600')->toContain('fit=crop');
    });

    // No secret is involved, which is the whole point: a browser can build one of
    // these too. What stands in for a signature is that the parameters are bounded.
    it('carries no signature', function () use ($media) {
        expect(new GlideTransformer(new GlideConfig())->url($media, ['w' => 600]))->not->toContain('s=');
    });

    // Two templates asking for the same crop should emit the same string, so a CDN
    // and a browser cache see one image rather than several.
    it('produces the same URL whatever the parameter order', function () use ($media) {
        $transformer = new GlideTransformer(new GlideConfig());

        expect($transformer->url($media, ['w' => 600, 'h' => 400]))
            ->toBe($transformer->url($media, ['h' => 400, 'w' => 600]));
    });

    // A template may write any number; the URL it gets back is on the grid. Without
    // this the template is where you find out the grid exists, by way of a 400.
    it('snaps a size onto the grid', function () use ($media) {
        expect(new GlideTransformer(new GlideConfig())->url($media, ['w' => 601]))->toContain('w=700');
    });

    it('drops a parameter that is not part of a transform', function () use ($media) {
        // `q` especially: quality is a decision about the site, and every value it
        // could take multiplies the cache.
        $url = new GlideTransformer(new GlideConfig())->url($media, ['w' => 600, 'q' => 30, 'blur' => 90]);

        expect($url)->not->toContain('q=30')->not->toContain('blur');
    });

    it('lets an image from outside this site\'s media through', function () {
        $ailleurs = 'https://ailleurs.test/photo.jpg';

        expect(new GlideTransformer(new GlideConfig())->url($ailleurs, ['w' => 400]))->toBe($ailleurs);
    });

    it('lets a URL with no transform asked for through', function () use ($media) {
        expect(new GlideTransformer(new GlideConfig())->url($media, []))->toBe($media);
    });

    // A URL the route would refuse is a broken image on the page. Handing back the
    // source is a heavier page; handing back a 400 is a hole in the layout.
    it('returns the source rather than a URL the route would refuse', function () use ($media) {
        $transformer = new GlideTransformer(new GlideConfig());

        expect($transformer->url($media, ['w' => 99999]))->toBe($media);
        expect($transformer->url($media, ['w' => 600, 'fit' => 'stretch']))->toBe($media);
    });

    // A `..` in a path that reaches the filesystem is how a transformer becomes a
    // way to read what it was never pointed at.
    it('refuses a path that climbs out of the media', function () {
        $remonte = 'http://example.com/wp-content/uploads/../../wp-config.php';

        expect(new GlideTransformer(new GlideConfig())->url($remonte, ['w' => 100]))->toBe($remonte);
    });
});

describe('GlideConfig::normalise', function () {
    $config = new GlideConfig();

    it('keeps a transform that is on the grid', function () use ($config) {
        expect($config->normalise(['w' => '600', 'h' => '400', 'fit' => 'crop', 'fm' => 'webp']))->toBe([
            'w' => '600',
            'h' => '400',
            'fit' => 'crop',
            'fm' => 'webp',
        ]);
    });

    // The bound on the cache. Without it `?w=601` is a different file from `?w=600`
    // and the number an image can have is the number of integers.
    it('snaps a size up to the step', function () use ($config) {
        expect($config->normalise(['w' => '601']))->toBe(['w' => '700']);
        expect($config->normalise(['w' => '600']))->toBe(['w' => '600']);
    });

    it('refuses a size past the maximum', function () use ($config) {
        expect($config->normalise(['w' => '2600']))->toBe(['w' => '2600']);
        expect($config->normalise(['w' => '2601']))->toBeNull();
        expect($config->normalise(['w' => '99999']))->toBeNull();
    });

    // `{{ image_url(image, { h: (600 * ratio)|round }) }}` hands over a float,
    // because that is what Twig's `round` returns. Refusing it made every image in
    // the demo fall back to its source URL — right for an impossible transform,
    // and silent, which is how a page rendered perfectly with no transforms in it.
    it('accepts a size that arrived as a float', function () use ($config) {
        expect($config->normalise(['w' => 600.0]))->toBe(['w' => '600']);
        expect($config->normalise(['h' => 799.6]))->toBe(['h' => '800']);
    });

    it('refuses a size that is not one', function () use ($config) {
        expect($config->normalise(['w' => NAN]))->toBeNull();
        expect($config->normalise(['w' => INF]))->toBeNull();
        expect($config->normalise(['w' => '-1']))->toBeNull();
        expect($config->normalise(['w' => '0']))->toBeNull();
        expect($config->normalise(['w' => '1e9']))->toBeNull();
        expect($config->normalise(['w' => '600; rm -rf']))->toBeNull();
    });

    it('refuses a fit or a format it does not serve', function () use ($config) {
        expect($config->normalise(['w' => '600', 'fit' => 'stretch']))->toBeNull();
        expect($config->normalise(['w' => '600', 'fm' => 'gif']))->toBeNull();
    });

    // This is the one that matters. `Server::getAllParams()` ends with
    // `array_merge($all, $params)`, so a key that reaches Glide overrides anything
    // configured here — an unknown parameter is not ignored, it wins.
    it('drops every parameter that is not part of a transform', function () use ($config) {
        expect($config->normalise(['w' => '600', 'q' => '100', 'blur' => '100', 'p' => 'x', 's' => 'y']))->toBe([
            'w' => '600',
        ]);
    });

    it('refuses a request that asks for no dimension at all', function () use ($config) {
        expect($config->normalise([]))->toBeNull();
        expect($config->normalise(['fit' => 'crop']))->toBeNull();
    });

    // A shape rather than a value: `?w[]=1` arrives as an array and every string
    // function downstream would take it badly.
    it('refuses a parameter that is not a scalar', function () use ($config) {
        expect($config->normalise(['w' => ['600']]))->toBeNull();
    });
});

describe('GlideConfig::cachePath', function () {
    // The invariant the webserver rule stands on: nginx assembles this path out of
    // named arguments, so if PHP ever wrote it elsewhere every hit would silently
    // miss and boot WordPress, with the pages still looking perfectly correct.
    it('spells the transform into the path', function () {
        expect(GlideConfig::cachePath('2016/06/photo.jpg', [
            'w' => '600',
            'h' => '400',
            'fit' => 'crop',
            'fm' => 'webp',
        ]))
            ->toBe('2016/06/photo.jpg/600x400-crop-webp');
    });

    it('leaves a slot empty for a parameter that was not given', function () {
        expect(GlideConfig::cachePath('a.jpg', ['w' => '600']))->toBe('a.jpg/600x--');
    });

    // Named arguments, not the raw query string — otherwise the same transform
    // written in another order is a second copy of the same file.
    it('is the same path whatever the parameter order', function () {
        expect(GlideConfig::cachePath('a.jpg', ['h' => '400', 'w' => '600']))
            ->toBe(GlideConfig::cachePath('a.jpg', ['w' => '600', 'h' => '400']));
    });

    // The path reaches a filesystem, so a separator in a slot is a way out of the
    // directory. `normalise()` refuses these first; this is the second lock.
    it('keeps a slot from becoming a path', function () {
        expect(GlideConfig::cachePath('a.jpg', ['w' => '../../etc/passwd']))->not->toContain('/etc/');
        expect(GlideConfig::cachePath('a.jpg', ['fit' => 'a/b']))->not->toContain('a/b');
    });

    // Asking the Server rather than the static method, because the wiring between
    // them is the fragile part: Glide rebinds the callable onto itself, and
    // `Closure::bind()` returns null for a static closure or one made from a
    // method. Glide reads that as "Invalid cache path callable" and every image
    // 404s — with nothing else in this file noticing, since none of it builds a
    // Server. A linter that adds `static` there is enough to cause it.
    it('is the path the Glide server actually uses', function () {
        $uploads = sys_get_temp_dir() . '/foehn-glide-' . getmypid();
        @mkdir($uploads . '/2016/06', 0o777, true);
        file_put_contents($uploads . '/2016/06/photo.jpg', 'not really a jpeg');

        $GLOBALS['wp_stub_upload_basedir'] = $uploads;

        try {
            $params = ['w' => '600', 'h' => '400', 'fit' => 'crop', 'fm' => 'webp'];

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
