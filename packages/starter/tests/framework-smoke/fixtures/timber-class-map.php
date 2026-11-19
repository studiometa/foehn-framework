<?php

declare(strict_types=1);

/**
 * A Timber model over a post type of the same name, from discovery to the object
 * Timber builds.
 *
 * A package registers a post type with its own model, and a project maps a subclass
 * to it with #[AsTimberModel]. The discoveries apply in the order WordPress runs them:
 * the Timber models on `after_setup_theme`, the post types on `init`. The project
 * class must still win.
 *
 * Run by `TimberClassMapTest` through `wp eval-file`. One line of JSON, because
 * `Site::wp()` hands back the last line of a command.
 */

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Studiometa\Foehn\Models\Post;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Timber\Timber;

#[AsPostType(name: 'smoke_class_map', singular: 'Smoke', plural: 'Smokes')]
class SmokeClassMapPackageModel extends Post {}

#[AsTimberModel(name: 'smoke_class_map')]
final class SmokeClassMapProjectModel extends SmokeClassMapPackageModel {}

$location = new DiscoveryLocation('Smoke\\', __DIR__);

$models = new TimberModelDiscovery(new DiscoveryProblems());
$models->discover($location, new ClassReflector(SmokeClassMapProjectModel::class));

$postTypes = new PostTypeDiscovery(new DiscoveryProblems());
$postTypes->discover($location, new ClassReflector(SmokeClassMapPackageModel::class));

$models->apply();
$postTypes->apply();

$postId = wp_insert_post(['post_type' => 'smoke_class_map', 'post_title' => 'Smoke', 'post_status' => 'publish']);

$post = Timber::get_post($postId);
$posts = Timber::get_posts(['post_type' => 'smoke_class_map', 'posts_per_page' => 1])->to_array();

wp_delete_post($postId, true);

echo wp_json_encode([
    'post' => is_object($post) ? $post::class : null,
    'posts' => array_map(static fn(object $item): string => $item::class, $posts),
]);
