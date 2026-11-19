<?php

declare(strict_types=1);

/**
 * The accessibility checks of the pre-publish panel, as the block editor enqueues them.
 *
 * Sets the block post editor as the current screen and fires
 * `enqueue_block_editor_assets` as the editor screen does. The script must come
 * from the web root, where the installer copied it, with its configuration inlined
 * before it.
 *
 * Run by `PrepublishChecksTest` through `wp eval-file`. One line of output, because
 * `Site::wp()` hands back the last line of a command.
 */

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$screen = WP_Screen::get('post');
$screen->is_block_editor(true);
$screen->set_current_screen();

do_action('enqueue_block_editor_assets');

$script = wp_scripts()->query('foehn-prepublish-checks');
$before = wp_scripts()->get_inline_script_data('foehn-prepublish-checks', 'before');

printf(
    'enqueued=%d file=%d config=%d',
    (int) wp_script_is('foehn-prepublish-checks', 'enqueued'),
    (int) ($script !== false && is_file(WP_CONTENT_DIR . '/foehn/prepublish-checks.js')),
    (int) str_contains($before, 'window.foehnPrepublishChecks = {"blockPublication":false'),
);
