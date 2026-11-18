<?php

declare(strict_types=1);

/**
 * The accessibility checks of the pre-publish panel, as the block editor enqueues them.
 *
 * Fires `enqueue_block_editor_assets` as the editor screen does. The script must come
 * from the web root, where the installer copied it, with its configuration inlined
 * before it.
 *
 * Run by `PrepublishChecksTest` through `wp eval-file`. One line of output, because
 * `Site::wp()` hands back the last line of a command.
 */

do_action('enqueue_block_editor_assets');

$script = wp_scripts()->query('foehn-prepublish-checks');
$before = wp_scripts()->get_inline_script_data('foehn-prepublish-checks', 'before');

printf(
    'enqueued=%d file=%d config=%d',
    (int) wp_script_is('foehn-prepublish-checks', 'enqueued'),
    (int) ($script !== false && is_file(WP_CONTENT_DIR . '/foehn/prepublish-checks.js')),
    (int) str_contains($before, 'window.foehnPrepublishChecks = {"blockPublication":false'),
);
