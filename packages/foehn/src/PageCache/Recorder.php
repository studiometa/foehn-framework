<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Closure;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Config\PageCacheStorage;

/**
 * The write side: capture the rendered page, and store it if it may be stored.
 *
 * The buffer opens on `template_redirect` at priority 0, which is before anything
 * chooses a template. Føhn's own `TemplateControllerDiscovery` runs on
 * `template_include` at priority 5 — inside that buffer — so nothing about rendering
 * has to change to be cacheable.
 *
 * The buffer is only opened for a request that could still turn out cacheable. A feed,
 * a REST response or a logged-in page is decided before `ob_start()` rather than
 * wrapped and discarded, because wrapping a streaming response in a buffer is a
 * behaviour change and this feature has no business making one.
 */
final class Recorder
{
    private bool $capturing = false;

    /** The write a bucket store does after the response is sent. */
    private ?Closure $pending = null;

    public function __construct(
        private readonly PageCacheConfig $config,
        private readonly PageStore $store,
        private readonly Bypass $bypass,
    ) {}

    /**
     * Start capturing on `template_redirect`.
     */
    public function register(): void
    {
        add_action('template_redirect', $this->start(...), 0);
    }

    /**
     * Open the buffer, unless this request is already known not to be cacheable.
     */
    public function start(): void
    {
        if ($this->capturing) {
            return;
        }

        $reason = $this->bypass->forContext($_SERVER, $_COOKIE, $_POST);

        if ($reason !== null) {
            DebugHeaders::send($this->config, DebugHeaders::STATE_BYPASS, $reason);

            return;
        }

        $this->capturing = true;

        ob_start($this->onFlush(...));
    }

    /**
     * Store the rendered page when it is eligible, and return it either way.
     *
     * The return value is the response. Nothing here may change what a visitor sees
     * beyond the one marker comment, which is appended to the file and to the response
     * both, so that the stored bytes and the live bytes are the same bytes.
     */
    public function onFlush(string $body): string
    {
        $status = is_int(http_response_code()) ? (int) http_response_code() : 200;
        $headers = headers_sent() ? [] : headers_list();
        $reason = $this->bypass->forResponse($body, $status, $headers, $_SERVER, $_COOKIE, $_POST);

        if ($reason !== null) {
            DebugHeaders::send($this->config, DebugHeaders::STATE_BYPASS, $reason);

            return $body;
        }

        $key = $this->bypass->key($_SERVER);

        if ($key === null) {
            DebugHeaders::send($this->config, DebugHeaders::STATE_BYPASS, BypassReason::Path);

            return $body;
        }

        $body .= self::marker();

        // The status and the headers travel with the body now. A 404 stored without its
        // status came back as a 200, which is a soft 404 that nothing downstream can see
        // — and a page's own headers vanished on every hit.
        $this->write(fn(): bool => $this->store->put($key, $body, $status, $headers));

        DebugHeaders::send($this->config, DebugHeaders::STATE_MISS);

        return $body;
    }

    /**
     * Write now on disk, or after the response is sent to a bucket.
     *
     * A PUT is an HTTP request, and a miss must not wait for it. The buffer is flushed
     * from WordPress's own shutdown hook, so a shutdown function registered here runs
     * after it, when the whole response is out.
     *
     * @param Closure(): bool $write
     */
    private function write(Closure $write): void
    {
        if ($this->config->storage !== PageCacheStorage::Bucket) {
            $write();

            return;
        }

        $this->pending = $write;

        register_shutdown_function($this->finish(...));
    }

    /**
     * Send the response to the visitor, then do the pending write.
     */
    public function finish(): void
    {
        $write = $this->pending;
        $this->pending = null;

        if ($write === null) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $write();
    }

    /**
     * The comment that says which render a page is.
     *
     * On the stored file and on the live response both, so "is this the cached one?" is
     * answerable from a browser rather than from an SSH session.
     */
    public static function marker(?int $timestamp = null): string
    {
        return "\n<!-- foehn cache: " . gmdate('c', $timestamp ?? time()) . " -->\n";
    }
}
