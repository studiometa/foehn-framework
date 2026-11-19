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
 * With a `@type` as argument, it prints the first node of that type instead, one line
 * per property: a value as it is, a list as its count and the `@type` of its items, a
 * node as its `name` (or its `@type`).
 *
 *     curl -sk https://foehn-demo.ddev.site/about/ | php tests/smoke/json-ld.php FAQPage
 *     @type FAQPage
 *     @id https://foehn-demo.ddev.site/about/#faq
 *     mainEntity 4 Question
 *
 * Run on the host, not through `wp eval-file`: it parses what a visitor gets. A script
 * that is not valid JSON exits with status 1, and so does a type that no node has.
 */

$html = (string) stream_get_contents(STDIN);
$head = strpos($html, '</head>');
$wanted = $argv[1] ?? null;

preg_match_all(
    '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s',
    $html,
    $matches,
    PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
);

/**
 * @param mixed $value
 */
function json_ld_summary(mixed $value): string
{
    if (!is_array($value)) {
        return is_scalar($value) ? (string) $value : '';
    }

    if (array_is_list($value)) {
        $types = array_unique(array_map(static fn(mixed $item): string => (string) ($item['@type'] ?? ''), $value));

        return trim(count($value) . ' ' . implode(' ', $types));
    }

    return (string) ($value['name'] ?? $value['@type'] ?? '');
}

foreach ($matches as $match) {
    try {
        $json = json_decode($match[1][0], true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, 'Invalid JSON-LD: ' . $exception->getMessage() . "\n" . substr($match[1][0], 0, 400) . "\n");
        exit(1);
    }

    $nodes = is_array($json) ? $json['@graph'] ?? [$json] : [];

    if ($wanted === null) {
        $types = [];

        foreach ($nodes as $node) {
            array_push($types, ...(array) ($node['@type'] ?? []));
        }

        printf("%s %s\n", $head !== false && $match[0][1] < $head ? 'head' : 'body', implode(' ', $types));

        continue;
    }

    foreach ($nodes as $node) {
        if (in_array($wanted, (array) ($node['@type'] ?? []), true)) {
            foreach ($node as $property => $value) {
                printf("%s %s\n", $property, json_ld_summary($value));
            }

            exit(0);
        }
    }
}

if ($wanted !== null) {
    fwrite(STDERR, "No {$wanted} node in the JSON-LD of the page.\n");
    exit(1);
}
