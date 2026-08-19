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

#[AsCliCommand(
    name: 'cache:config',
    description: 'Print the server configuration that serves the page cache',
    longDescription: <<<'DOC'
        ## OPTIONS

        --server=<server>
        : Which server to render for.
        ---
        options:
          - nginx
          - apache
        ---

        [--write]
        : Write the file instead of printing it. nginx goes to
        config/nginx/foehn-page-cache.conf; Apache is merged into web/.htaccess between
        its own markers.

        ## DESCRIPTION

        The snippets bake in policy only the loaded configuration knows — which cookies
        bypass, which query args are ignored, where the cache root is. That is why this is
        a WP-CLI command and not an installer step: the installer knows paths, and paths
        alone would give you a snippet that disagrees with the PHP writing the files.

        Re-run it after changing app/page-cache*.config.php. `wp foehn cache:status`
        reports when an installed snippet was generated from a different policy.

        ## EXAMPLES

            # Look at what would be installed
            wp foehn cache:config --server=nginx

            # Write it
            wp foehn cache:config --server=nginx --write

            # Merge the Apache block into web/.htaccess
            wp foehn cache:config --server=apache --write
        DOC,
)]
final class PageCacheConfigCommand implements CliCommandInterface
{
    /** Where `--write` puts the nginx snippet, relative to the project root. */
    public const NGINX_PATH = 'config/nginx/foehn-page-cache.conf';

    public function __construct(
        private readonly WpCli $cli,
        private readonly PageCacheConfig $config,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $server = $assocArgs['server'] ?? '';

        if (!in_array($server, ['nginx', 'apache'], true)) {
            $this->cli->error('Pass --server=nginx or --server=apache.');

            return;
        }

        $snippet = $server === 'nginx'
            ? new NginxSnippet($this->config)->render()
            : new ApacheSnippet($this->config)->render();

        if ($snippet === null) {
            $this->cli->error(
                'The configured cache path is not under the document root, so no server can reach it by filename. '
                . 'The advanced-cache.php drop-in still serves it.',
            );

            return;
        }

        if (($assocArgs['write'] ?? null) === null) {
            $this->cli->line($snippet);

            return;
        }

        $server === 'nginx' ? $this->writeNginx($snippet) : $this->writeApache($snippet);
    }

    private function writeNginx(string $snippet): void
    {
        $root = SnippetPolicy::documentRoot();

        if ($root === null) {
            $this->cli->error('Could not work out the project root from ABSPATH.');

            return;
        }

        $path = dirname($root) . '/' . self::NGINX_PATH;

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0o755, true) && !is_dir(dirname($path))) {
            $this->cli->error('Could not create ' . dirname($path));

            return;
        }

        if (file_put_contents($path, $snippet . "\n") === false) {
            $this->cli->error('Could not write ' . $path);

            return;
        }

        $this->cli->success("Wrote {$path}");
        $this->cli->log('Include it inside your server { } block, then reload nginx.');
    }

    private function writeApache(string $snippet): void
    {
        $root = SnippetPolicy::documentRoot();

        if ($root === null) {
            $this->cli->error('Could not work out the document root from ABSPATH.');

            return;
        }

        $path = $root . '/.htaccess';
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        // The starter ships no .htaccess and DISALLOW_FILE_MODS stops WordPress writing
        // one, so a first run has to supply the permalink rules as well — installing the
        // cache block alone would leave every URL on the site 404ing.
        if (!str_contains($existing, '# BEGIN WordPress')) {
            $existing = rtrim($existing . "\n\n" . ApacheSnippet::wordPressBlock()) . "\n";
        }

        $merged = new ApacheSnippet($this->config)->insertInto($existing, $snippet);

        if (file_put_contents($path, $merged) === false) {
            $this->cli->error('Could not write ' . $path);

            return;
        }

        $this->cli->success("Updated {$path}");
    }
}
