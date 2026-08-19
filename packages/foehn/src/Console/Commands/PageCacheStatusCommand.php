<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\PageCache\Store;

#[AsCliCommand(name: 'cache:status', description: 'Show static page cache status', longDescription: <<<'DOC'
    ## DESCRIPTION

    Reports whether the page cache is on, where it stores files, and what is in it.

    ## EXAMPLES

        # Show page cache status
        wp foehn cache:status
    DOC)]
final class PageCacheStatusCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly PageCacheConfig $config,
        private readonly Store $store,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $this->cli->line('Page Cache Status');
        $this->cli->line('=================');
        $this->cli->line('');

        $environment = PageCacheConfig::environment();

        $this->cli->log('Enabled: ' . ($this->config->enabled ? 'Yes' : 'No'));
        $this->cli->log(sprintf(
            'Environment: %s (allowed: %s)',
            $environment,
            implode(', ', $this->config->environments),
        ));
        $this->cli->log('Cache path: ' . $this->store->root());
        $this->cli->log('TTL: ' . ($this->config->ttl > 0 ? $this->config->ttl . 's' : 'none — purge-driven'));
        $this->cli->log('Debug headers: ' . ($this->config->wantsDebugHeaders() ? 'Yes' : 'No'));

        $stats = $this->store->stats();

        $this->cli->line('');
        $this->cli->log(sprintf('Files: %d (%s)', $stats['files'], $this->bytes($stats['bytes'])));
        $this->cli->log('Oldest entry: ' . $this->age($stats['oldest']));
        $this->cli->log('Newest entry: ' . $this->age($stats['newest']));
        $this->cli->line('');

        if (!$this->config->enabled) {
            $this->cli->log('Nothing is written or served. Enable it in app/page-cache.config.php.');

            return;
        }

        if (!$this->config->allowsEnvironment($environment)) {
            $this->cli->warning("The page cache is enabled but inert in the '{$environment}' environment.");

            return;
        }

        $this->cli->success('The page cache is active.');
    }

    private function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        foreach (['KB', 'MB', 'GB'] as $index => $unit) {
            $scaled = $bytes / (1024 ** ($index + 1));

            if ($scaled < 1024 || $unit === 'GB') {
                return sprintf('%.1f %s', $scaled, $unit);
            }
        }

        return $bytes . ' B';
    }

    private function age(?int $timestamp): string
    {
        if ($timestamp === null) {
            return '—';
        }

        return sprintf('%s (%ds ago)', gmdate('c', $timestamp), max(0, time() - $timestamp));
    }
}
