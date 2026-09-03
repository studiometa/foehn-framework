<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\CacheDirectory;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Verification\VerificationResult;

/**
 * If the page cache is on, it can actually write.
 *
 * The failure this exists for is quiet by construction: a cache that cannot write its
 * files does not break the site, it just renders every page every time. The site stays
 * correct and gets slower, which is the one kind of fault nobody gets paged for.
 *
 * The usual cause is ownership. WP-CLI run as root leaves root-owned files under
 * `wp-content/cache/`, and PHP — which is not root — can no longer write there. That is
 * why the Docker cron runner goes out of its way to run as the application user, and why
 * this check reads writability rather than merely the configuration.
 *
 * **Caching off is a pass, not a skip.** A project is allowed not to want a page cache,
 * and the deployment gate has nothing to object to. What it reports in that case is how
 * many stored pages an earlier release left behind, because those are still being served
 * by the nginx snippet or the drop-in if either is still installed — and
 * `wp foehn cache:clear` still removes them.
 *
 * **No path reaches the report.** An absolute path is both unstable between machines and
 * more than a build artifact should carry, so the findings are booleans and a count.
 */
final readonly class PageCacheCheck implements Check
{
    public const NAME = 'page-cache-storage';

    public function __construct(
        private PageCacheConfig $config,
        private Store $store,
    ) {}

    public function run(): array
    {
        $configured = $this->config->enabled;
        $effective = $configured && $this->config->allowsEnvironment(EnvironmentCheck::EXPECTED);

        if (!$configured) {
            $stale = $this->store->stats()['files'];

            return [VerificationResult::pass(
                self::NAME,
                $stale === 0
                    ? 'The page cache is disabled and nothing is stored.'
                    : sprintf(
                        'The page cache is disabled. %d page%s left by an earlier release remain '
                        . 'and are still clearable with `wp foehn cache:clear`.',
                        $stale,
                        $stale === 1 ? '' : 's',
                    ),
                ['configured' => false, 'effective' => false, 'stale_pages' => $stale],
            )];
        }

        if (!$effective) {
            // Enabled, and switched off for production by its own `environments` list.
            // A contradiction rather than a preference: the setting says yes and the site
            // it is deployed to is excluded, so nothing is cached and nobody said so.
            return [VerificationResult::fail(
                self::NAME,
                'The page cache is enabled but its environments list excludes production, '
                . 'so nothing is cached or served here.',
                [
                    'configured' => true,
                    'effective' => false,
                    'production_allowed' => false,
                    'environments' => $this->config->environments,
                ],
            )];
        }

        $contained = $this->rootIsContained();
        $writable = $this->rootIsWritable();
        $details = [
            'configured' => true,
            'effective' => true,
            'root_contained' => $contained,
            'root_writable' => $writable,
        ];

        if (!$contained) {
            return [VerificationResult::fail(
                self::NAME,
                'The page cache root does not resolve inside itself — a symlink is redirecting it '
                . 'out of the configured directory.',
                $details,
            )];
        }

        if (!$writable) {
            return [VerificationResult::fail(
                self::NAME,
                'The page cache is enabled but its directory is not writable, so every page is '
                . 'rendered from scratch. Check for root-owned files under wp-content/cache/.',
                $details,
            )];
        }

        return [VerificationResult::pass(
            self::NAME,
            'The page cache is active and its storage is writable.',
            $details,
        )];
    }

    /**
     * Whether the root still resolves to itself.
     *
     * Asked through {@see CacheDirectory} rather than with a string comparison, because
     * that class is the one that answers it for every write: a symlink at the root would
     * make every stored page land outside the tree the containment rules protect, and
     * `realpath()` is the only thing that can see it.
     */
    private function rootIsContained(): bool
    {
        return new CacheDirectory($this->store->root())->resolve('') !== null;
    }

    /**
     * Whether the cache can be written to, creating the root if it does not exist yet.
     *
     * A root that does not exist is not a failure — the first cacheable request creates
     * it. What matters is whether the parent would let it, so the nearest existing
     * ancestor is the thing tested.
     */
    private function rootIsWritable(): bool
    {
        $path = $this->store->root();

        while ($path !== '' && $path !== '/' && !file_exists($path)) {
            $path = dirname($path);
        }

        return is_dir($path) && is_writable($path);
    }
}
