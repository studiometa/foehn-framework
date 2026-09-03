<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Admin;

use Closure;
use Studiometa\Foehn\PageCache\Invalidator;

/**
 * The three cache mutations an administrator can perform from the browser.
 *
 * The dashboard's two buttons and the admin bar's three items all post here, to
 * `admin-post.php`, and this is the only code in the framework that turns a browser
 * request into a deletion. Everything about the design is a consequence of that:
 *
 * **The browser posts an intent, never a target.** "Clear this post" travels as a post
 * id and nothing else. No URL, no filesystem path, no cache key — and not because those
 * would be validated carefully, but because a parameter that does not exist cannot be
 * got wrong. The permalink is resolved here, from the database, through
 * `get_permalink()`. A handler that accepted a path would be a remote file-deletion
 * endpoint one validation bug away from working.
 *
 * **Four checks, in this order, before anything is deleted.** A non-POST request is
 * refused first: `admin-post.php` fires `admin_post_{action}` for a GET too, so a link
 * in an email would otherwise clear a production cache for whoever clicked it. Then the
 * capability, then a nonce minted for *this* action — a nonce is a per-action token here
 * rather than a per-page one, so the one on "clear everything" cannot authorise "clear
 * this post". Then, when one is present, the post id.
 *
 * **Nothing but {@see Invalidator} is called.** No {@see \Studiometa\Foehn\PageCache\Store},
 * no `unlink()`, no path handling. The invalidator is the one place that turns a URL into
 * the files it owns, and a second implementation here is how an admin button and an
 * automatic purge come to disagree about which files a page owns.
 *
 * **The answer carries a code and a count, and nothing else.** No message built from
 * input, no path, no URL — the redirect target is a validated referrer or the dashboard,
 * and the only things added to it are one of the fixed strings below and an integer.
 *
 * The handlers stay registered while page caching is off, for the same reason
 * {@see Invalidator} does not read `PageCacheConfig::$enabled`: a release that had the
 * cache on leaves files behind, and the operator switching it off is precisely the one
 * who needs them gone.
 */
final class CacheActions
{
    /** Clear the whole page cache. */
    public const FLUSH = 'foehn_cache_flush';

    /** Clear every section-cache entry, leaving whole pages alone. */
    public const FLUSH_SECTIONS = 'foehn_cache_flush_sections';

    /** Clear one post's page and every variant it owns. */
    public const FORGET_POST = 'foehn_cache_forget_post';

    /**
     * The admin page a rejected referrer falls back to.
     *
     * Named here rather than on {@see Dashboard}, because the fallback has to exist for
     * the handlers to be safe whether or not anything renders a page under it: a redirect
     * target that depends on a page class is a redirect target that can be absent.
     */
    public const PAGE = 'foehn';

    /** The capability every one of these actions requires. */
    public const CAPABILITY = 'manage_options';

    /** The POST field carrying the post id, for {@see CacheActions::FORGET_POST}. */
    public const POST_ID_FIELD = 'foehn_post_id';

    /** Query arg naming what happened, on the page the browser lands on. */
    public const RESULT_ARG = 'foehn_cache_result';

    /** Query arg carrying how many stored response bodies went. */
    public const COUNT_ARG = 'foehn_cache_removed';

    /** The cache was cleared, and the count says by how much. */
    public const CLEARED = 'cleared';

    /**
     * The request was authorised but the clear did not happen.
     *
     * A post whose permalink is not a URL this cache can key gets this rather than
     * `cleared` with a count of zero. {@see Invalidator::forgetUrl()} draws that line and
     * a handler that flattened it would report success for work it did not do.
     */
    public const FAILED = 'failed';

    /**
     * How a handler stops once it has answered.
     *
     * `exit` is correct in production — a handler that returns lets `admin-post.php`
     * carry on rendering after a `Location` header — and untestable, because it takes the
     * test runner with it. So stopping is a collaborator: the default exits, and the unit
     * suite hands in one that records. That is the only reason this seam exists, and it
     * is not a filter or an extension point.
     *
     * @var Closure(): void
     */
    private Closure $halt;

    /**
     * @param (Closure(): void)|null $halt How to stop. Null means `exit`.
     */
    public function __construct(
        private readonly Invalidator $invalidator,
        ?Closure $halt = null,
    ) {
        $this->halt = $halt ?? static function (): void {
            exit();
        };
    }

    /**
     * Wire the three handlers.
     *
     * `admin_post_{action}` only, and never `admin_post_nopriv_{action}`: an anonymous
     * caller has no business reaching a handler whose first answer would be a refusal.
     */
    public function register(): void
    {
        add_action('admin_post_' . self::FLUSH, $this->flush(...));
        add_action('admin_post_' . self::FLUSH_SECTIONS, $this->flushSections(...));
        add_action('admin_post_' . self::FORGET_POST, $this->forgetPost(...));
    }

    /**
     * Empty the page cache.
     */
    public function flush(): void
    {
        if (!$this->authorized(self::FLUSH)) {
            $this->deny();

            return;
        }

        $this->finish(self::CLEARED, $this->invalidator->flush());
    }

    /**
     * Empty the section cache, and only the section cache.
     */
    public function flushSections(): void
    {
        if (!$this->authorized(self::FLUSH_SECTIONS)) {
            $this->deny();

            return;
        }

        $this->finish(self::CLEARED, $this->invalidator->flushSections());
    }

    /**
     * Clear one post's page, its 404, its keyed variants and its section fragments.
     *
     * Not paginated, and that matches the automatic purge: `<!--nextpage-->` splits a post
     * at `/slug/2/`, which is a different page rather than the same one repeated — see
     * {@see \Studiometa\Foehn\PageCache\PurgeTargets::forPost()}. A button that cleared
     * more than `save_post` does would be a second definition of what a post owns.
     */
    public function forgetPost(): void
    {
        if (!$this->authorized(self::FORGET_POST)) {
            $this->deny();

            return;
        }

        $id = $this->requestedPostId();

        if ($id === null) {
            $this->deny();

            return;
        }

        // Resolved here, from the row. This is the line the whole class is arranged
        // around: the request said which post, and the server says which URL. The cast
        // absorbs the `false` a post type without a permalink answers with.
        $permalink = (string) get_permalink($id);

        if ($permalink === '') {
            $this->finish(self::FAILED);

            return;
        }

        $removed = $this->invalidator->forgetUrl($permalink);

        // Null is "that permalink cannot be a cache key", which is a failure and not a
        // clear of nothing. Reporting it as success would hide a permalink structure this
        // cache refuses from the only person able to notice.
        $this->finish($removed === null ? self::FAILED : self::CLEARED, $removed);
    }

    /**
     * The nonce action string an action's forms must mint their token against.
     *
     * The action's own name, so every action has its own: a token created for one is
     * invalid for the others, which is what stops a form that clears the section cache
     * from being replayed as a form that clears everything.
     */
    public static function nonceAction(string $action): string
    {
        return $action;
    }

    /**
     * Where a form posts.
     */
    public static function endpoint(): string
    {
        return admin_url('admin-post.php');
    }

    /**
     * The Føhn dashboard's own URL.
     */
    public static function dashboardUrl(): string
    {
        return admin_url('admin.php?page=' . self::PAGE);
    }

    /**
     * Whether this request may perform this action at all.
     *
     * Method, capability, nonce — in that order, and each one on its own is enough to
     * refuse. Nothing here reports *which* check failed: the only caller who benefits
     * from that detail is whoever is probing the endpoint.
     */
    private function authorized(string $action): bool
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        if (!current_user_can(self::CAPABILITY)) {
            return false;
        }

        return $this->nonceIsValid($action);
    }

    /**
     * Whether the request carries a nonce minted for this action.
     *
     * Read from `$_POST` only. A nonce accepted from the query string would let a `GET`
     * with a leaked token through on any server that fills `$_REQUEST` from both.
     */
    private function nonceIsValid(string $action): bool
    {
        $nonce = $_POST['_wpnonce'] ?? null;

        if (!is_string($nonce) || $nonce === '') {
            return false;
        }

        return wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), self::nonceAction($action)) !== false;
    }

    /**
     * The post id this request names, or null when it does not name a usable one.
     *
     * `absint()` first, so anything that is not a positive integer — an empty field, a
     * word, a negative number, a path — becomes `0` and is refused rather than coerced
     * into somebody's post. The zero is checked before anything else asks WordPress about
     * it: `is_post_publicly_viewable(0)` would answer for the *global* post, so a request
     * with no id at all would clear whichever page the admin happened to have loaded.
     *
     * Then one question, which answers two: `is_post_publicly_viewable()` is false both
     * for an id no row has and for a post no visitor could have been served. A draft has
     * no cached page to clear, so an id that resolves to one is a mistake or a probe
     * rather than something to work around.
     *
     * An id and not a `WP_Post`, because that is all the rest of the handler needs —
     * `get_permalink()` takes one — and a method that returns the smaller thing is one
     * less place for the two to disagree about which post is meant.
     */
    private function requestedPostId(): ?int
    {
        $id = absint($_POST[self::POST_ID_FIELD] ?? 0);

        if ($id === 0) {
            return null;
        }

        return is_post_publicly_viewable($id) ? $id : null;
    }

    /**
     * Refuse, saying as little as is useful, and stop.
     *
     * 403 rather than a redirect. A redirect would put a "something went wrong" notice on
     * a page the caller may not be allowed to see, and a rejected mutation is not a
     * navigation.
     */
    private function deny(): void
    {
        wp_die(esc_html__('You are not allowed to clear this cache.', 'foehn'), '', ['response' => 403]);

        ($this->halt)();
    }

    /**
     * Send the browser back where it came from, carrying the outcome, and stop.
     */
    private function finish(string $result, ?int $removed = null): void
    {
        wp_safe_redirect($this->target($result, $removed));

        ($this->halt)();
    }

    /**
     * The page to land on, with the outcome and nothing else attached.
     *
     * The referrer is validated by WordPress rather than trusted or pattern-matched:
     * `wp_validate_redirect()` refuses a host the site does not allow and hands back the
     * fallback, which is how a crafted `_wp_http_referer` becomes a trip to the dashboard
     * instead of an open redirect. Our own two args are stripped from it first, so
     * pressing a button twice does not accumulate them.
     */
    private function target(string $result, ?int $removed): string
    {
        $fallback = self::dashboardUrl();
        // The cast absorbs "there was no referrer at all", which `wp_get_referer()`
        // reports as `false`; a referrer WordPress refuses comes back as the fallback.
        $base = wp_validate_redirect((string) wp_get_referer(), $fallback);

        if ($base === '') {
            $base = $fallback;
        }

        return add_query_arg(
            [
                self::RESULT_ARG => $result,
                self::COUNT_ARG => (int) $removed,
            ],
            remove_query_arg([self::RESULT_ARG, self::COUNT_ARG], $base),
        );
    }
}
