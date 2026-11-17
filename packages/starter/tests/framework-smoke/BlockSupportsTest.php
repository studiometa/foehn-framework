<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Block supports on the front end of a Føhn block.
 *
 * The unit suite has no WordPress, so it cannot run the HTML API or core's block
 * supports. Only a real site shows the support attributes on the template's root.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('puts the block support attributes on the root element of the template', function () {
    $html = (string) Site::wp('wp eval-file tests/framework-smoke/fixtures/block-supports.php');

    // The host has no WordPress, so the root tag is read with a pattern, not the HTML API.
    expect(preg_match('/^<section\b([^>]*)>/', $html, $root))->toBe(1, "the template root is not first in: {$html}");
    expect(preg_match('/\sclass="([^"]*)"/', $root[1], $class))->toBe(1, "no class on: {$root[0]}");
    expect(preg_match('/\sstyle="([^"]*)"/', $root[1], $style))->toBe(1, "no style on: {$root[0]}");

    $classes = explode(' ', $class[1]);
    expect($classes)->toContain('smoke', 'alignwide', 'smoke-extra', 'has-background', 'wp-block-smoke-supports');
    // The 7.1 responsive state, which core's own render_block filter adds.
    expect($class[1])->toMatch('/\bwp-states-[0-9a-f]{8}\b/');
    expect($root[1])->toContain('id="smoke-anchor"');
    // The template's own style first, the supports after it.
    expect($style[1])
        ->toStartWith('color:black; ')
        ->toContain('background-color:#ff0000')
        ->toContain('padding-top:2rem');
});
