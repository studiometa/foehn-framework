<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The meta box of #[AsPostMeta] keys with a control, in a real WordPress.
 *
 * The unit suite draws the form and saves it over stubs. Only a real site runs
 * `add_meta_box()`, the nonce, `save_post_{$postType}` and the `sanitize_callback`
 * that `update_post_meta()` applies.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('reads back a value saved through the meta box form', function () {
    $output = (string) Site::wp('wp eval-file tests/framework-smoke/fixtures/post-meta-box.php');
    $result = json_decode($output, true);

    expect($result)->toBeArray($output);
    expect($result['title'])->toBe('Fields');
    expect($result['field'])->toBeTrue();
    expect($result['nonce'])->toBeTrue();
    expect($result['saved'])->toBe([
        // The text control's sanitiser, sanitize_text_field().
        'subtitle' => "It's on",
        // The hidden value of an unchecked box, stored as false.
        'featured' => '',
        // The key's own sanitize method, run by update_post_meta(), with its backslash.
        'code' => 'AB\\1',
        // A backslash survives the save: update_post_meta() unslashes once.
        'path' => 'C:\\path',
    ]);
    // A save without the form leaves the value alone.
    expect($result['kept'])->toBe("It's on");
});
