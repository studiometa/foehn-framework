<?php

declare(strict_types=1);

/**
 * A published page with one block scheduled to show a few seconds from now.
 *
 * The date is written as the "Schedule" panel writes it: a `datetime-local` value with
 * no time zone, read in the site time zone.
 *
 * Run by `ScheduledBlockTest` through `wp eval-file`, with the delay in seconds as its
 * argument. One line of output, the page id and its path, because `Site::wp()` hands
 * back the last line of a command.
 */

/** @var list<string> $args */
$delay = (int) ($args[0] ?? 10);
$from = new DateTimeImmutable('@' . (time() + $delay))
    ->setTimezone(wp_timezone())
    ->format('Y-m-d\TH:i:s');

$id = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Block schedule ' . uniqid(),
    'post_content' =>
        '<!-- wp:paragraph -->'
            . "\n"
            . '<p>smoke-always-block</p>'
            . "\n"
            . '<!-- /wp:paragraph -->'
            . "\n\n"
            . sprintf('<!-- wp:paragraph %s -->', wp_json_encode(['visibleFrom' => $from]))
            . "\n"
            . '<p>smoke-scheduled-block</p>'
            . "\n"
            . '<!-- /wp:paragraph -->',
]);

echo $id . ' ' . (string) wp_parse_url((string) get_permalink($id), PHP_URL_PATH);
