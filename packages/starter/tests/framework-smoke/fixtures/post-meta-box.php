<?php

declare(strict_types=1);

/**
 * The #[AsPostMeta] meta box, from discovery to a saved value read back.
 *
 * The fixture class goes through `PostMetaDiscovery` as a theme model does, so
 * `register_meta()` and the two hooks are the real ones. The form is the HTML the
 * meta box prints on the edit screen; the save posts that form's nonce with new
 * values, as the classic editor and the block editor do, and `wp_update_post()`
 * fires `save_post_{$postType}`.
 *
 * Run by `PostMetaBoxTest` through `wp eval-file`. One line of JSON, because
 * `Site::wp()` hands back the last line of a command.
 */

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Studiometa\Foehn\PostTypes\PostMetaBox;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

#[AsPostType(name: 'smoke_meta_box', singular: 'Smoke', plural: 'Smokes')]
#[AsPostMeta(key: 'smoke_subtitle', control: 'text', label: 'Subtitle')]
#[AsPostMeta(key: 'smoke_featured', type: 'boolean', control: 'checkbox', label: 'Featured')]
#[AsPostMeta(key: 'smoke_code', control: 'text', sanitize: 'upper')]
final class SmokeMetaBoxModel
{
    public static function upper(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}

require_once ABSPATH . 'wp-admin/includes/template.php';

register_post_type('smoke_meta_box', ['public' => false, 'show_ui' => true]);

$discovery = new PostMetaDiscovery(new DiscoveryProblems());
$discovery->discover(new DiscoveryLocation('Smoke\\', __DIR__), new ClassReflector(SmokeMetaBoxModel::class));
$discovery->apply();

wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);

$postId = wp_insert_post(['post_type' => 'smoke_meta_box', 'post_title' => 'Smoke', 'post_status' => 'draft']);
update_post_meta($postId, 'smoke_featured', true);

do_action('add_meta_boxes_smoke_meta_box', get_post($postId));

/** @var array<string, mixed> $box */
$box = $GLOBALS['wp_meta_boxes']['smoke_meta_box']['advanced']['default'][PostMetaBox::ID] ?? [];

ob_start();
call_user_func($box['callback'], get_post($postId));
$form = (string) ob_get_clean();

preg_match('/name="' . PostMetaBox::NONCE . '" value="([^"]+)"/', $form, $nonce);

// What the browser posts: the nonce, the hidden value of the unchecked box, and the
// text fields. Slashed, as WordPress slashes $_POST.
$_POST = wp_slash([
    PostMetaBox::NONCE => $nonce[1] ?? '',
    PostMetaBox::FIELD => [
        'smoke_subtitle' => "It's <b>on</b>",
        'smoke_featured' => '0',
        'smoke_code' => 'ab-1',
    ],
]);
wp_update_post(['ID' => $postId]);
$saved = [
    'subtitle' => get_post_meta($postId, 'smoke_subtitle', true),
    'featured' => get_post_meta($postId, 'smoke_featured', true),
    'code' => get_post_meta($postId, 'smoke_code', true),
];

// A save without the meta box, as a REST client or an import makes one.
$_POST = [];
wp_update_post(['ID' => $postId, 'post_title' => 'Smoke again']);
$kept = get_post_meta($postId, 'smoke_subtitle', true);

wp_delete_post($postId, true);

echo wp_json_encode([
    'title' => $box['title'] ?? null,
    'field' => str_contains($form, 'name="foehn_meta[smoke_subtitle]"'),
    'nonce' => isset($nonce[1]),
    'saved' => $saved,
    'kept' => $kept,
]);
