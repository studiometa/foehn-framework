<?php

declare(strict_types=1);

/**
 * The admin cache handlers, run inside a booted WordPress via `wp eval-file`.
 *
 * What no HTTP client in this suite can reach: an authorised call. Minting a live nonce
 * needs a logged-in administrator's session, so the request is assembled here instead —
 * a `manage_options` user set as current, a real nonce, a real post id in `$_POST` — and
 * the handler is invoked directly.
 *
 * The property under test is the one the design rests on: **the request supplies an id
 * and the server decides which URL that means.** So the request also carries a `url` and
 * a `path` pointing at a *different* cached page, and that page has to survive.
 *
 * `CacheActions` is constructed here rather than taken from the container, because the
 * container's instance ends the request with `exit` — correct in production, and it would
 * take `wp eval-file` with it before anything could be asserted. The halt is the only
 * thing replaced.
 *
 * Exits non-zero on the first failure so CI stops with a readable message.
 */

use Studiometa\Foehn\Admin\CacheActions;
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Store;

// `wp eval-file` runs this inside a function, so the results live in an object rather
// than in globals a top-level `global` statement would not reach.
$results = new class {
    public int $passed = 0;

    /** @var list<string> */
    public array $failures = [];

    public function same(string $label, mixed $expected, mixed $actual): void
    {
        if ($expected === $actual) {
            $this->passed++;

            return;
        }

        $this->failures[] = sprintf(
            "%s\n    expected: %s\n    actual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true),
        );
    }

    public function true(string $label, bool $actual): void
    {
        $this->same($label, true, $actual);
    }
};

// ──────────────────────────────────────────────
// A post to clear, and a page that must survive
// ──────────────────────────────────────────────

$postId = (int) wp_insert_post([
    'post_title' => 'Føhn admin cache smoke',
    'post_content' => str_repeat('Content long enough to be storable. ', 20),
    'post_status' => 'publish',
    'post_type' => 'post',
]);

$results->true('a post to clear exists', $postId > 0);

$permalink = (string) get_permalink($postId);
$results->true('the post has a permalink', $permalink !== '');

/** @var Store $store */
$store = Kernel::get(Store::class);
$host = (string) parse_url((string) home_url('/'), PHP_URL_HOST);

$target = CacheKey::create($host, (string) parse_url($permalink, PHP_URL_PATH));
$bystander = CacheKey::create($host, '/');

$results->true('the post permalink can be keyed', $target !== null);
$results->true('the home page can be keyed', $bystander !== null);

if ($target === null || $bystander === null) {
    // Stopping here rather than crashing on a null: everything below stores and reads
    // those keys, and a TypeError says less about what went wrong than the two failures
    // just recorded.
    printf(
        "%d passed, %d FAILED — no cache key, nothing further could run\n",
        $results->passed,
        count($results->failures),
    );

    exit(1);
}

$store->put($target, '<html><body>' . str_repeat('t', 300) . '</body></html>');
$store->put($bystander, '<html><body>' . str_repeat('b', 300) . '</body></html>');

$results->true('the post page is stored', $store->has($target));
$results->true('the home page is stored', $store->has($bystander));

// ──────────────────────────────────────────────
// An authorised request, pointed elsewhere
// ──────────────────────────────────────────────

$administrator = get_users(['role' => 'administrator', 'number' => 1]);
$userId = $administrator === [] ? 0 : (int) $administrator[0]->ID;

$results->true('there is an administrator to act as', $userId > 0);

wp_set_current_user($userId);
$results->true('the current user may clear the cache', current_user_can(CacheActions::CAPABILITY));

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => CacheActions::FORGET_POST,
    '_wpnonce' => wp_create_nonce(CacheActions::nonceAction(CacheActions::FORGET_POST)),
    CacheActions::POST_ID_FIELD => (string) $postId,
    // Everything a request would have to supply for the handler to be pointed at the
    // wrong page. None of it is read.
    'url' => (string) home_url('/'),
    'path' => $store->root() . '/' . $host,
    'permalink' => (string) home_url('/'),
];

$halted = 0;
new CacheActions(Kernel::get(Invalidator::class), static function () use (&$halted): void {
    $halted++;
})->forgetPost();

$results->same('the handler stopped once', 1, $halted);

// The assertion this file exists for.
$results->true('the post page the server resolved is gone', !$store->has($target));
$results->true('the page the request named is still there', $store->has($bystander));

// ──────────────────────────────────────────────
// Clean up
// ──────────────────────────────────────────────

wp_delete_post($postId, true);
$store->flush();
$_POST = [];
unset($_SERVER['REQUEST_METHOD']);

// ──────────────────────────────────────────────
// Report
// ──────────────────────────────────────────────

if ($results->failures !== []) {
    printf("%d passed, %d FAILED\n\n", $results->passed, count($results->failures));

    foreach ($results->failures as $failure) {
        printf("  ✗ %s\n\n", $failure);
    }

    exit(1);
}

printf("%d assertions passed\n", $results->passed);
