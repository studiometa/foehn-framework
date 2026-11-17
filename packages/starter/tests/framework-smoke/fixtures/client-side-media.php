<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\CleanImageSizes;

/**
 * The image sizes WordPress 7.1 hands to its client-side media processing, with
 * `CleanImageSizes` on.
 *
 * Run by `ClientSideMediaTest` through `wp eval-file`. Prints one line of JSON, because
 * `Site::wp()` hands back the last line of a command:
 *
 * - `sizes`: the sizes the REST index lists for the browser to generate;
 * - `medium` and `thumbnail`: the error code the sideload endpoint answers for a file
 *   of that size, or null when it accepts the size name.
 */

// The starter does not turn the hook on, so this run does.
add_filter('intermediate_image_sizes', [new CleanImageSizes(), 'removeUnnecessarySizes']);

// WP-CLI is not a secure context, where WordPress turns client-side processing off.
// The sideload route exists only when it is on.
add_filter('wp_client_side_media_processing_enabled', '__return_true');

$administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) ($administrators[0] ?? 0));

$index = rest_do_request(new WP_REST_Request('GET', '/'))->get_data();

/**
 * The error code of a sideload of one size. The size name is validated with the
 * other parameters, before the attachment is read, so no attachment is needed: an
 * accepted name fails later, on the missing attachment, with another code.
 */
$sideload = static function (string $size): ?string {
    $request = new WP_REST_Request('POST', '/wp/v2/media/' . PHP_INT_MAX . '/sideload');
    $request->set_param('image_size', $size);

    $data = rest_do_request($request)->get_data();
    $code = $data['data']['details']['image_size']['code'] ?? null;

    return is_string($code) ? $code : null;
};

echo
    json_encode([
        'sizes' => array_keys($index['image_sizes'] ?? []),
        'medium' => $sideload('medium'),
        'thumbnail' => $sideload('thumbnail'),
    ])
;
