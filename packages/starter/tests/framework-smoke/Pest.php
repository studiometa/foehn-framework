<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Client;
use Studiometa\Foehn\Smoke\Support\Response;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Helpers for the page-cache smoke suite.
 *
 * Every one of these goes over the wire or touches the filesystem. Nothing here is
 * mocked, which is the point: the unit suites already prove the rules, and what they
 * cannot prove is that a web server reads the file PHP wrote.
 */

/**
 * Request a path on the site under test.
 *
 * @param array<string, string> $cookies
 */
function smokeGet(string $path = '/', array $cookies = []): Response
{
    return Client::get(Site::url() . $path, $cookies);
}

/**
 * Request a path twice and hand back both responses, which is the shape of almost every
 * assertion here: one render, then whatever answers the second time.
 *
 * @return array{0: Response, 1: Response}
 */
function smokeGetTwice(string $path = '/'): array
{
    return [smokeGet($path), smokeGet($path)];
}

/**
 * Activate a plugin that the suite tests Føhn with.
 *
 * The plugins are installed with Composer, never at run time: CI requires them in the
 * starter before `composer install` (the integration-starter job of
 * `.github/workflows/ci.yml`, which holds their versions). Require the same ones in a
 * local install. A plugin that is not there fails the file, rather than skipping it.
 */
function smokeActivatePlugin(string $slug): void
{
    $result = Site::run('wp plugin activate ' . escapeshellarg($slug));

    if ($result['status'] !== 0) {
        throw new RuntimeException(sprintf(
            "Could not activate %s. Install it with Composer in the starter: composer require --dev wpackagist-plugin/%s\n%s",
            $slug,
            $slug,
            $result['output'],
        ));
    }
}

/**
 * Deactivate a plugin and run its uninstall routine, so the next file starts from a new
 * install of it. The files stay: Composer owns them.
 */
function smokeUninstallPlugin(string $slug): void
{
    Site::wp('wp plugin uninstall ' . escapeshellarg($slug) . ' --deactivate --skip-delete');
}

/**
 * Every `<script type="application/ld+json">` of a page, decoded, with its class and
 * where it starts. A script that is not valid JSON fails the test.
 *
 * @return list<array{class: string, offset: int, json: array<string, mixed>}>
 */
function smokeJsonLd(string $html): array
{
    preg_match_all(
        '#<script type="application/ld\+json"(?: class="([^"]*)")?[^>]*>(.*?)</script>#s',
        $html,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
    );

    return array_map(static fn(array $match): array => [
        'class' => $match[1][0] ?? '',
        'offset' => $match[0][1],
        'json' => json_decode($match[2][0], true, flags: JSON_THROW_ON_ERROR),
    ], $matches);
}

/**
 * The `@type` values of the graph of a decoded JSON-LD script.
 *
 * @param array<string, mixed> $json
 * @return list<string>
 */
function smokeJsonLdTypes(array $json): array
{
    return array_merge(...array_map(
        static fn(array $node): array => (array) ($node['@type'] ?? []),
        $json['@graph'] ?? [$json],
    ));
}

/**
 * The URL of the post the starter installs, which the starter's PostJsonLd describes.
 */
function smokeJsonLdPostUrl(): string
{
    return (string) Site::wp('wp post list --post_type=post --name=hello-world --field=url');
}

/**
 * The body of a URL requested with a mu-plugin that adds a node, as a block does when
 * it renders.
 */
function smokeJsonLdWithBlock(string $url): string
{
    $plugin = Site::path('web/wp-content/mu-plugins/smoke-json-ld-block.php');
    copy(__DIR__ . '/fixtures/json-ld-block.php', $plugin);

    try {
        return Client::get($url . '?smoke-json-ld-block=1')->body;
    } finally {
        unlink($plugin);
    }
}

/**
 * Fill the cache for a path and hand back the stored response.
 */
function smokeWarm(string $path = '/'): Response
{
    smokeGet($path);

    return smokeGet($path);
}

/**
 * Assert a response was answered the way it should have been.
 *
 * The failure message carries the whole cache verdict, because "expected HIT, got
 * BYPASS" without the reason is a message that sends you to the source rather than to
 * the problem.
 */
function expectCache(Response $response, string $state, ?string $via = null): void
{
    expect($response->cache())->toBe($state, 'wrong cache state — ' . $response->describe());

    if ($via !== null) {
        expect($response->via())->toBe($via, 'wrong reader answered — ' . $response->describe());
    }
}

/**
 * The `<!-- foehn cache: … -->` marker carries a timestamp, so two renders of one page
 * are never byte-identical. Identical bodies are therefore the only proof a page was
 * served from a file rather than rendered again — a HIT header alone can be a lie.
 */
function expectSameBody(Response $first, Response $second, string $message): void
{
    expect(md5($second->body))->toBe(md5($first->body), $message);
}

function expectDifferentBody(Response $first, Response $second, string $message): void
{
    expect(md5($second->body))->not->toBe(md5($first->body), $message);
}

/**
 * Assert a response was rendered rather than taken from the cache.
 *
 * The marker is appended only when a page is stored, so its absence is proof this
 * response is neither a stored page nor one on its way to becoming one. Body comparison
 * cannot show this: two renders of a page with no marker are byte-identical, which is
 * exactly what makes a bypassed page look like a cached one.
 */
function expectRendered(Response $response, string $message): void
{
    // Through str_contains() rather than ->toContain(): that expectation is variadic, so
    // a second argument is read as another needle rather than as a failure message.
    expect(str_contains($response->body, Response::MARKER))->toBeFalse($message . ' — ' . $response->describe());
}

/**
 * Assert a response carries the marker, so it either came from a file or has just been
 * written to one.
 */
function expectMarked(Response $response, string $message): void
{
    expect(str_contains($response->body, Response::MARKER))->toBeTrue($message . ' — ' . $response->describe());
}
