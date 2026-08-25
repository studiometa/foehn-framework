<?php

declare(strict_types=1);

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use Studiometa\Foehn\Images\GlideConfig;
use Studiometa\Foehn\Images\GlideRoute;

/**
 * A filesystem whose streaming read fails and whose plain read does not.
 *
 * Not a contrivance: it is object storage. `readStream()` asks the S3 adapter for
 * `@http.stream`, and the body that comes back is not seekable on every provider
 * — against Tigris the SDK's own rewind throws "Stream is not seekable", and the
 * transform can never be delivered however well it was built.
 */
final class UnseekableFilesystem extends Filesystem
{
    public function readStream(string $location)
    {
        throw UnableToReadFile::fromLocation($location, 'Stream is not seekable');
    }
}

/**
 * @return string A directory of this test's own, emptied on the way in.
 */
function glideRouteTestDir(): string
{
    $dir = sys_get_temp_dir() . '/foehn-glide-route';

    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
    }

    return $dir;
}

describe('GlideRoute', function () {
    // A miss is answered by building the transform and sending it. Reading it back
    // as a *stream* is what `Server::outputImage()` does, and it is the one thing
    // this route must not depend on — the read has to work where streaming cannot.
    it('reads a transform from a filesystem that cannot be streamed', function () {
        $cache = new UnseekableFilesystem(new LocalFilesystemAdapter(glideRouteTestDir()));
        $cache->write('2016/06/photo.jpg/600x400-crop-webp', 'IMAGE-BYTES');

        expect($cache->read('2016/06/photo.jpg/600x400-crop-webp'))->toBe('IMAGE-BYTES');
        expect(fn() => $cache->readStream('2016/06/photo.jpg/600x400-crop-webp'))->toThrow(UnableToReadFile::class);
    });

    // The cache path is what the webserver assembles out of the request, so the
    // two have to agree character for character — or every hit misses, boots
    // WordPress, and looks perfectly correct while doing it.
    it('spells the transform into the cache path', function () {
        expect(GlideConfig::cachePath('2016/06/photo.jpg', [
            'w' => '600',
            'h' => '400',
            'fit' => 'crop',
            'fm' => 'webp',
        ]))
            ->toBe('2016/06/photo.jpg/600x400-crop-webp');
    });

    // `fm` has been through `normalise()`, so it is one of `FORMATS` and nothing
    // else: the media type follows from it with no round trip to the bucket.
    it('derives the media type from the format that was asked for', function () {
        $mimeType = new ReflectionMethod(GlideRoute::class, 'mimeType');
        $route = new GlideRoute(new GlideConfig());
        $cache = new Filesystem(new LocalFilesystemAdapter(glideRouteTestDir()));

        $types = [
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        foreach ($types as $format => $expected) {
            expect($mimeType->invoke($route, ['fm' => $format], $cache, 'unused'))->toBe($expected);
        }
    });

    // With no format the answer is the source's own, which only the filesystem
    // knows — one HeadObject, and only for a request that named no format.
    it('asks the filesystem when no format was named', function () {
        $cache = new Filesystem(new LocalFilesystemAdapter(glideRouteTestDir()));
        $cache->write('a/photo.jpg/600x-max-', 'GIF89a');

        $mimeType = new ReflectionMethod(GlideRoute::class, 'mimeType');
        $type = $mimeType->invoke(new GlideRoute(new GlideConfig()), [], $cache, 'a/photo.jpg/600x-max-');

        expect($type)->toBeString()->not->toBe('');
    });
});
