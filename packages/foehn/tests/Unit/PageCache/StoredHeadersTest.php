<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\StoredHeaders;

/**
 * What a stored page is allowed to remember about its own response.
 *
 * The first test is the one that matters: a cookie belongs to the visitor it was minted
 * for, and a cache that replayed one would hand that session to everybody who asked for
 * the page next.
 */

it('never stores a Set-Cookie header', function () {
    $kept = StoredHeaders::keep([
        'Set-Cookie: wordpress_logged_in_abc=titouan|123|hash; path=/; httponly',
        'Link: </style.css>; rel=preload; as=style',
    ]);

    expect($kept)->toBe(['Link: </style.css>; rel=preload; as=style']);
});

it('drops the headers the cache computes for itself', function (string $header) {
    // Replaying a recorded ETag or Content-Length would contradict the file being sent.
    expect(StoredHeaders::keep([$header]))->toBe([]);
})->with([
    ['Cache-Control: max-age=600'],
    ['ETag: "stale"'],
    ['Last-Modified: Mon, 01 Sep 2026 00:00:00 GMT'],
    ['Content-Length: 12'],
    ['Content-Type: text/plain'],
    ['Vary: User-Agent'],
    ['Set-Cookie: a=b'],
    ['X-Foehn-Cache: HIT'],
]);

it('keeps the headers a page set for itself', function (string $header) {
    expect(StoredHeaders::keep([$header]))->toBe([$header]);
})->with([
    ['Link: </a.css>; rel=preload'],
    ['X-Robots-Tag: noindex, nofollow'],
    ['Content-Security-Policy: default-src \'self\''],
    ['X-Frame-Options: SAMEORIGIN'],
]);

it('refuses a header that is not one', function (string $line) {
    // The file sits on disk between the write and the send, so what comes back is
    // validated again — a header read from a writable directory and sent unchecked is a
    // header injection with extra steps.
    expect(StoredHeaders::keep([$line]))->toBe([]);
})->with([
    'no colon' => ['not a header'],
    'a smuggled second header' => ["X-One: a\r\nX-Two: b"],
    'a bare newline' => ["X-One: a\nX-Two: b"],
    'an empty line' => ['']
]);

it('round-trips through the stored form', function () {
    $headers = ['Link: </a.css>; rel=preload', 'X-Robots-Tag: noindex'];

    expect(StoredHeaders::decode(StoredHeaders::encode($headers)))->toBe($headers);
});

it('re-filters on the way out, not only on the way in', function () {
    // A file somebody edited, or one written by an older version with a wider list.
    expect(StoredHeaders::decode("Set-Cookie: session=stolen\nX-Robots-Tag: noindex\n"))
        ->toBe(['X-Robots-Tag: noindex']);
});
