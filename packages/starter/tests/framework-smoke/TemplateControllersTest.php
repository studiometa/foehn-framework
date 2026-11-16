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
