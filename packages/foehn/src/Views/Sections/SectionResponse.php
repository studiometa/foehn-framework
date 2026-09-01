<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Sections;

use Studiometa\Foehn\PageCache\Bypass;

/**
 * What a section request is answered with: a status, a set of headers, and a body.
 *
 * Separate from the discovery that decides *what* to render, because the two answer
 * different questions and only this one has an opinion the page cache shares. Every path
 * returns the empty string, which is what the `template_include` filter wants back — the
 * filter's way of saying there is nothing left to include.
 */
final readonly class SectionResponse
{
    /**
     * The page each error status gets, keyed by status.
     *
     * A title and nothing else. Exception details belong in the log: this document is
     * served to whoever asked, and an error page that names a class or a file is a map of
     * the application handed out for free.
     */
    private const TITLES = [
        400 => 'Invalid section request',
        404 => 'Section not found',
        405 => 'Method not allowed',
    ];

    public function __construct(
        private SectionRequest $request,
        private Bypass $bypass,
    ) {}

    /**
     * Send a rendered selection.
     */
    public function send(string $body, int $status = 200): string
    {
        if (!headers_sent()) {
            status_header($status);

            foreach ($this->headers($body, $status) as $header) {
                header($header, true);
            }
        }

        // A HEAD gets the headers and nothing else, which is the whole of what HEAD means.
        if (!$this->request->isHead()) {
            echo $body;
        }

        return '';
    }

    /**
     * Send an error page for a section request that cannot be answered.
     */
    public function error(int $status): string
    {
        $title = self::TITLES[$status] ?? 'Unable to render sections';

        return $this->send(
            sprintf(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%1$s</title></head><body><h1>%1$s</h1></body></html>',
                $title,
            ),
            $status,
        );
    }

    /**
     * The headers a section response carries.
     *
     * Public and returned rather than only sent, because the one rule here that is not
     * obvious has to be assertable without a web server: `header()` leaves nothing a unit
     * test can read, and a page-cache rule nobody can test is a rule that goes wrong the
     * first time {@see Bypass} changes underneath it.
     *
     * `X-Robots-Tag` is unconditional. A section response is a fragment of a page, and a
     * fragment indexed on its own is a search result that leads to half a page — so it is
     * refused whether the response is cached or not. It survives a cache hit because the
     * store keeps a response's own headers and the drop-in replays them; the nginx fast
     * path has none to replay and derives the same header from `$arg_foehn_sections`.
     *
     * `Cache-Control: private, no-store` is the conditional one, and the condition is
     * {@see Bypass} rather than a second rule written here. A section request is stored
     * under exactly the rules a page is, so the only responses that still have to say
     * `no-store` are the ones the cache was never going to keep: a logged-in visitor, a
     * `HEAD`, an error status, an environment the cache is off in. Asking `Bypass` is what
     * stops this answer and the recorder's from drifting apart — if they ever did, a
     * response would say `no-store` and be written to disk anyway, or say nothing and
     * never be written, and both fail silently.
     *
     * @return list<string>
     */
    public function headers(string $body, int $status): array
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'X-Robots-Tag: noindex, nofollow',
            ...($status === 405 ? ['Allow: GET, HEAD'] : []),
        ];

        if ($this->bypass->forResponse($body, $status, $headers, $_SERVER, $_COOKIE, $_POST) !== null) {
            $headers[] = 'Cache-Control: private, no-store';
        }

        return $headers;
    }
}
