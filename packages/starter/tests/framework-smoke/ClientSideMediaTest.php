<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `CleanImageSizes` and the client-side media processing of WordPress 7.1.
 *
 * In the block editor the browser cuts the sub-sizes and uploads each one to
 * `/wp/v2/media/{id}/sideload`. The unit suite proves which names the filter keeps;
 * what only a real WordPress can show is that the list the browser reads, and the
 * names the sideload endpoint accepts, both come from that filter.
 */

/**
 * @return array{sizes: list<string>, medium: string|null, thumbnail: string|null}|null
 */
function clientSideMedia(): ?array
{
    $output = Site::wp('wp eval-file tests/framework-smoke/fixtures/client-side-media.php');

    return $output === null ? null : json_decode($output, true);
}

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('lists no removed core size for the browser to generate', function () {
    $sizes = clientSideMedia()['sizes'] ?? null;

    expect($sizes)->toContain('thumbnail');
    expect($sizes)->not->toContain('medium');
    expect($sizes)->not->toContain('large');
    expect($sizes)->not->toContain('1536x1536');
});

it('refuses a sideload of a removed size', function () {
    expect(clientSideMedia()['medium'] ?? null)->toBe('rest_not_in_enum');
});

it('accepts the name of a kept size', function () {
    $result = clientSideMedia();

    expect($result)->toBeArray();
    expect($result['thumbnail'])->toBeNull();
});
