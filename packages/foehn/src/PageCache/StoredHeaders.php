<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * The response headers a stored page carries, and the ones it must never carry.
 *
 * A cache that stores only a body serves a different response than the one it recorded:
 * a `Link:` preload, a `Content-Security-Policy` computed for that page, an
 * `X-Robots-Tag` a section response sets — all present on the miss, all gone on every
 * hit. Storing them beside the body closes that gap for the PHP reader.
 *
 * nginx cannot replay them. Its static file module has no notion of an embedded header
 * block, and `proxy_cache`'s stored format is nginx's own, written and read by the same
 * module. So the fast path keeps sending the headers the generated snippet derives from
 * the configuration, and the drop-in sends those *plus* what was recorded. That is an
 * asymmetry worth stating plainly rather than pretending away — see docs/guide/page-cache.md.
 */
final readonly class StoredHeaders
{
    /**
     * What is never stored, whatever the response said.
     *
     * `set-cookie` is the one that matters, and it is a security boundary rather than a
     * preference: a cookie belongs to the visitor it was minted for, and a cache that
     * replayed one would hand that visitor's session to everybody who asked for the page
     * afterwards. The rest are headers this cache computes itself — replaying a recorded
     * `etag` or `content-length` would contradict the file actually being sent.
     *
     * @var list<string>
     */
    public const DENIED = [
        'set-cookie',
        'cache-control',
        'etag',
        'last-modified',
        'content-length',
        'content-encoding',
        'transfer-encoding',
        'content-type',
        'vary',
        'date',
        'age',
        'connection',
        'keep-alive',
        'expires',
        'pragma',
        'x-foehn-cache',
        'x-foehn-cache-via',
        'x-foehn-cache-reason',
    ];

    /**
     * A header line this cache is willing to write, and later to send.
     *
     * Checked on the way in *and* on the way out. The file lives on disk between those
     * two moments, and a header sent from a file nobody re-validated is a header
     * injection waiting for a writable cache directory.
     */
    private const LINE = '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+:[^\r\n]*$/';

    /**
     * The headers worth storing, from `headers_list()`.
     *
     * @param list<string> $headers
     * @return list<string>
     */
    public static function keep(array $headers): array
    {
        $kept = [];

        foreach ($headers as $header) {
            if (preg_match(self::LINE, $header) !== 1) {
                continue;
            }

            $name = strtolower(trim(explode(':', $header, 2)[0]));

            if (in_array($name, self::DENIED, true)) {
                continue;
            }

            $kept[] = $header;
        }

        return $kept;
    }

    /**
     * The stored form: one header per line, and nothing else in the file.
     *
     * @param list<string> $headers
     */
    public static function encode(array $headers): string
    {
        return implode("\n", $headers) . "\n";
    }

    /**
     * The headers a stored file holds, re-validated line by line.
     *
     * @return list<string>
     */
    public static function decode(string $contents): array
    {
        return self::keep(array_values(array_filter(
            array_map('trim', explode("\n", $contents)),
            static fn(string $line): bool => $line !== '',
        )));
    }
}
