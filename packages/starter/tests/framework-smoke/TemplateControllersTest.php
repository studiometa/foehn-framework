<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Client;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Template controllers in front of the real WordPress template loader.
 *
 * The unit suite stubs every conditional tag. What only a real site shows is what the
 * template loader does with the path a controller hands back, and which requests reach
 * the controllers at all.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

/**
 * Create a published post and return its ID.
 */
function templateControllersPost(string $type, string $slug, string $content = ''): int
{
    Site::wp(sprintf('wp post delete $(wp post list --post_type=%s --name=%s --format=ids) --force', $type, $slug));

    return (int) Site::wp(sprintf(
        'wp post create --post_type=%s --post_status=publish --post_title=%s --post_name=%s --post_content=%s --porcelain',
        $type,
        escapeshellarg($slug),
        $slug,
        escapeshellarg($content),
    ));
}

it('leaves an oEmbed request to the embed template of WordPress', function () {
    $id = templateControllersPost('post', 'smoke-embed');
    $permalink = (string) Site::wp("wp post url {$id}");

    $response = Client::get(rtrim($permalink, '/') . '/embed/');

    expect($response->status)->toBe(200);
    // The embed template of core, not the single page of the theme.
    expect($response->body)->toContain('class="wp-embed');
    expect($response->body)->not->toContain('class="site-header"');

    Site::wp("wp post delete {$id} --force");
});

/**
 * The template a request matched, as `wp foehn trace` recorded it.
 */
function templateControllersMatched(string $url): ?string
{
    $id = Client::get($url)->header('X-Foehn-Trace');
    $output = Site::run("wp foehn trace {$id} --format=json")['output'];

    foreach (array_reverse(explode("\n", $output)) as $line) {
        $trace = json_decode(trim($line), true);

        if (is_array($trace)) {
            return $trace['template'] ?? null;
        }
    }

    return null;
}

it('matches the page template a page selects before its slug', function () {
    $id = templateControllersPost('page', 'smoke-page-template');
    Site::wp("wp post meta update {$id} _wp_page_template page-full-width.php");

    // The starter's PageController claims `page-*`, so the first name of the list wins.
    expect(templateControllersMatched((string) Site::wp("wp post url {$id}")))->toBe('page-full-width');

    Site::wp("wp post delete {$id} --force");
});

it('skips the controllers for a request that the skip filter names', function () {
    $plugin = Site::path('web/wp-content/mu-plugins/smoke-skip-template-controller.php');
    copy(__DIR__ . '/fixtures/skip-template-controller.php', $plugin);

    try {
        $url = (string) Site::url() . '/';

        // The starter's controllers render the layout with its header.
        expect(Client::get($url)->body)->toContain('class="site-header"');
        // Skipped, WordPress includes the index.php of the theme instead.
        expect(Client::get($url . '?smoke-skip-controllers=1')->body)
            ->toContain('No template controller matched this request.')
            ->not->toContain('class="site-header"');
    } finally {
        unlink($plugin);
    }
});
