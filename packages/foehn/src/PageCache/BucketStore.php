<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Closure;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Visibility;
use RuntimeException;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Helpers\UploadsBucket;

/**
 * The page cache in the `humanmade/s3-uploads` bucket, under `cache/pages/`.
 *
 * nginx serves hits from the bucket; see {@see ServerConfig\NginxSnippet}. Every instance
 * of a site reads and purges the same objects, which is the reason for this store: on
 * local disk, each instance has its own cache and a purge reaches one of them.
 *
 * The object keys are the paths {@see Store} writes under its root, so both readers of a
 * key agree. An S3 PUT is atomic, so there is no temporary object.
 *
 * What it does not store, because nothing reads it in bucket mode: the headers sidecar,
 * which only the drop-in replays, and a 404, which nginx never serves.
 *
 * The filesystem is made on first use. A site whose bucket is not configured boots and
 * renders every page, and `wp foehn cache:status` says why.
 */
final class BucketStore implements PageStore
{
    /** Where the pages are, in the bucket. */
    public const string PREFIX = 'cache/pages';

    /** The `Content-Type` nginx sends on a hit. The bucket returns what the PUT set. */
    public const string CONTENT_TYPE = 'text/html; charset=UTF-8';

    private ?FilesystemOperator $filesystem = null;

    /**
     * @param Closure(): FilesystemOperator $factory
     */
    public function __construct(
        private readonly PageCacheConfig $config,
        private readonly Closure $factory,
        private readonly string $location,
    ) {}

    /**
     * The store on the uploads bucket, with the client the s3-uploads plugin uses.
     */
    public static function forUploads(PageCacheConfig $config): self
    {
        $bucket = UploadsBucket::name();

        return new self(
            $config,
            static function () use ($bucket): FilesystemOperator {
                if ($bucket === null) {
                    throw new RuntimeException('S3_UPLOADS_BUCKET is not defined.');
                }

                if (!class_exists(AwsS3V3Adapter::class)) {
                    throw new RuntimeException('league/flysystem-aws-s3-v3 is not installed.');
                }

                return new Filesystem(new AwsS3V3Adapter(UploadsBucket::client(), $bucket, self::PREFIX));
            },
            sprintf('s3://%s/%s', $bucket ?? '(S3_UPLOADS_BUCKET is not defined)', self::PREFIX),
        );
    }

    public function root(): string
    {
        return $this->location;
    }

    /**
     * Store a page as a public-read object.
     *
     * Only a 200 is stored: nginx never serves the 404 filename, and the drop-in does
     * not read a bucket. The headers are not stored either; see the class comment.
     *
     * @param list<string> $headers
     */
    public function put(CacheKey $key, string $body, int $status = 200, array $headers = []): bool
    {
        if ($status !== 200) {
            return false;
        }

        $path = $key->relativePath();

        if (!CacheKey::isWritableFilename(basename($path))) {
            return false;
        }

        try {
            $this->filesystem()->write($path, $body, [
                'visibility' => Visibility::PUBLIC,
                'ContentType' => self::CONTENT_TYPE,
            ]);
        } catch (FilesystemException|RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * Delete the `index*` objects of one URL, and not the URLs below it.
     */
    public function forget(CacheKey $key): int
    {
        return $this->deleteWhere(
            $key->relativeDirectory(),
            false,
            static fn(string $name): bool => str_starts_with($name, 'index') && str_ends_with($name, '.html'),
        );
    }

    public function forgetPaginated(CacheKey $key): int
    {
        return $this->forget($key) + $this->deleteWhere($key->relativeDirectory() . '/page', true);
    }

    public function flush(): int
    {
        return $this->deleteWhere('', true);
    }

    public function flushSections(): int
    {
        return $this->deleteWhere('', true, CacheKey::isSectionEntry(...));
    }

    /**
     * Delete the objects older than the TTL, from the `lastModified` of a listing.
     *
     * One request for each 1,000 objects, and one per deleted object.
     */
    public function sweep(): int
    {
        if ($this->config->ttl <= 0) {
            return 0;
        }

        $limit = time() - $this->config->ttl;

        return $this->deleteWhere(
            '',
            true,
            null,
            static fn(FileAttributes $object): bool => (int) $object->lastModified() < $limit,
        );
    }

    public function stats(): array
    {
        return $this->measure();
    }

    public function sectionStats(): array
    {
        $measured = $this->measure(CacheKey::isSectionEntry(...));

        return ['files' => $measured['files'], 'bytes' => $measured['bytes']];
    }

    /**
     * Count the objects a filename predicate accepts, and their bytes.
     *
     * @param (callable(string): bool)|null $accepts Which filenames to count. Null counts all.
     * @return array{files: int, bytes: int, oldest: int|null, newest: int|null}
     */
    private function measure(?callable $accepts = null): array
    {
        $files = 0;
        $bytes = 0;
        $oldest = null;
        $newest = null;

        foreach ($this->objects('', true) as $object) {
            if ($accepts !== null && !$accepts(basename($object->path()))) {
                continue;
            }

            if (str_ends_with($object->path(), '.html')) {
                $files++;
            }

            $bytes += (int) $object->fileSize();
            $modified = (int) $object->lastModified();
            $oldest = $oldest === null ? $modified : min($oldest, $modified);
            $newest = $newest === null ? $modified : max($newest, $modified);
        }

        return ['files' => $files, 'bytes' => $bytes, 'oldest' => $oldest, 'newest' => $newest];
    }

    /**
     * Delete the objects under a prefix that the predicates accept.
     *
     * One object at a time, so the count is the objects that were really deleted. A
     * failed delete counts nothing, as on disk.
     *
     * @param (callable(string): bool)|null $name Which filenames to delete. Null deletes all.
     * @param (callable(FileAttributes): bool)|null $object Which objects to delete. Null deletes all.
     */
    private function deleteWhere(string $directory, bool $deep, ?callable $name = null, ?callable $object = null): int
    {
        $removed = 0;

        foreach ($this->objects($directory, $deep) as $entry) {
            $filename = basename($entry->path());

            if ($name !== null && !$name($filename) || $object !== null && !$object($entry)) {
                continue;
            }

            try {
                $this->filesystem()->delete($entry->path());
            } catch (FilesystemException|RuntimeException) {
                continue;
            }

            if (CacheKey::isWritableFilename($filename)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The objects under a prefix, read in one listing before anything is deleted.
     *
     * Empty when the bucket cannot be listed: a cache that cannot be read is a cache
     * with nothing in it, for every caller here.
     *
     * @return list<FileAttributes>
     */
    private function objects(string $directory, bool $deep): array
    {
        try {
            $objects = [];

            foreach ($this->filesystem()->listContents($directory, $deep) as $entry) {
                if (!$entry instanceof FileAttributes) {
                    continue;
                }

                $objects[] = $entry;
            }

            return $objects;
        } catch (FilesystemException|RuntimeException) {
            return [];
        }
    }

    private function filesystem(): FilesystemOperator
    {
        return $this->filesystem ??= ($this->factory)();
    }
}
