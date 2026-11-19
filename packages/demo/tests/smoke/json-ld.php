<?php

declare(strict_types=1);

/**
 * The JSON-LD of a page, read from stdin: one line per
 * `<script type="application/ld+json">`, with where it is (`head` or `body`) and the
 * `@type` of each node of its graph.
 *
 *     curl -sk https://foehn-demo.ddev.site/about/ | php tests/smoke/json-ld.php
 *     body FAQPage
 *
 * Run on the host, not through `wp eval-file`: it parses what a visitor gets. A script
 * that is not valid JSON exits with status 1.
 */

$html = (string) stream_get_contents(STDIN);
$head = strpos($html, '</head>');

preg_match_all(
    '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s',
    $html,
    $matches,
    PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
);

foreach ($matches as $match) {
    try {
        $json = json_decode($match[1][0], true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, 'Invalid JSON-LD: ' . $exception->getMessage() . "\n" . substr($match[1][0], 0, 400) . "\n");
        exit(1);
    }

    $types = [];

    foreach (is_array($json) ? $json['@graph'] ?? [$json] : [] as $node) {
        array_push($types, ...(array) ($node['@type'] ?? []));
    }

    printf("%s %s\n", $head !== false && $match[0][1] < $head ? 'head' : 'body', implode(' ', $types));
}
