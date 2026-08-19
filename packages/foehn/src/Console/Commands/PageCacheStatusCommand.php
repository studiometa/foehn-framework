<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\PageCache\ServerConfig\ApacheSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\NginxSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\SnippetPolicy;
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

        $this->reportReaders();
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

    /**
     * Which of the read paths are installed, and whether they still match the config.
     *
     * The thing most likely to bite this feature is a snippet generated from an older
     * policy: it keeps serving, it keeps answering HIT, and it is answering it under
     * rules the site no longer has. So the snippets carry a policy hash and this
     * compares it.
     */
    private function reportReaders(): void
    {
        $this->cli->log('Read paths:');

        $dropIn = defined('WP_CONTENT_DIR') ? constant('WP_CONTENT_DIR') . '/advanced-cache.php' : null;
        $hasDropIn = is_string($dropIn) && is_file($dropIn);
        $wpCache = defined('WP_CACHE') && (bool) constant('WP_CACHE');

        $this->cli->log(sprintf(
            '  %s drop-in (advanced-cache.php%s)',
            $hasDropIn && $wpCache ? '✓' : '·',
            $hasDropIn && !$wpCache ? ', but WP_CACHE is not true' : '',
        ));

        $root = SnippetPolicy::documentRoot();
        $project = $root === null ? null : dirname($root);

        $this->reportSnippet(
            'nginx',
            new NginxSnippet($this->config)->hash(),
            $project === null
                ? []
                : [
                    $project . '/' . PageCacheConfigCommand::NGINX_PATH,
                    // Where the starter keeps it, because ddev includes .ddev/nginx/*.conf
                    // inside its server block.
                    $project . '/.ddev/nginx/foehn-page-cache.conf',
                ],
        );

        $apache = $this->reportSnippet(
            'apache',
            new ApacheSnippet($this->config)->hash(),
            $root === null ? [] : [$root . '/.htaccess'],
        );

        // A keyed arg is served by nginx and by the drop-in, but not by mod_rewrite, which
        // cannot assemble the filename. Requests carrying one still get the right page —
        // they reach the drop-in — so this is slower rather than wrong, and worth saying
        // out loud before somebody reads it as a cache that stopped working.
        if ($apache && $this->config->getCacheQueryArgs() !== []) {
            $this->cli->log(sprintf('    note: %s served from PHP, not Apache — mod_rewrite cannot key a query arg', implode(
                ', ',
                array_keys($this->config->getCacheQueryArgs()),
            )));
        }
    }

    /**
     * Report one generated snippet, and whether it still matches the loaded config.
     *
     * @param list<string> $candidates
     * @return bool Whether one of the candidates is installed.
     */
    private function reportSnippet(string $label, string $hash, array $candidates): bool
    {
        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            if (!str_contains($contents, 'Foehn')) {
                continue;
            }

            $current = str_contains($contents, '# policy: ' . $hash);

            $this->cli->log(sprintf(
                '  %s %s (%s)%s',
                $current ? '✓' : '!',
                $label,
                $path,
                $current ? '' : ' — generated from a different config, re-run cache:config',
            ));

            return true;
        }

        $this->cli->log("  · {$label}");

        return false;
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
