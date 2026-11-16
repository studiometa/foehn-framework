<?php

declare(strict_types=1);

/**
 * Create or change a Rank Math redirection, the way its admin screens do.
 *
 * Run by `PageCacheWithRankMathTest` through `wp eval-file`, with the arguments:
 *
 * - `create <source> <destination> <comparison> <status>`: prints the new ID;
 * - `status <id> <active|inactive|trashed>`: the bulk action of the redirection list;
 * - `reset`: remove every redirection, and the lookup cache Rank Math keeps of them.
 *
 * Through Rank Math's own classes, so the writes reach the database as they do from
 * the admin.
 */

use RankMath\Redirections\DB;
use RankMath\Redirections\Redirection;

/** @var list<string> $args */
$action = $args[0] ?? '';

if ($action === 'create') {
    $redirection = Redirection::from([
        'sources' => [['pattern' => $args[1], 'comparison' => $args[3] ?? 'exact']],
        'url_to' => $args[2],
        'header_code' => 301,
        'status' => $args[4] ?? 'active',
    ]);

    echo (int) $redirection->save();

    return;
}

if ($action === 'status') {
    DB::change_status([(int) $args[1]], $args[2]);

    echo 'ok';
}

if ($action === 'reset') {
    global $wpdb;

    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}rank_math_redirections");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}rank_math_redirections_cache");

    echo 'ok';
}
