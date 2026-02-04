<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsPostType
{
    /**
     * @param string                $name          Post type name (slug)
     * @param string|null           $singular      Singular label
     * @param string|null           $plural        Plural label
     * @param bool                  $public        Whether the post type is public
     * @param bool                  $hasArchive    Whether to enable archive pages
     * @param bool                  $showInRest    Whether to expose in REST API (required for Gutenberg)
     * @param string|null           $menuIcon      Dashicon or custom icon URL
     * @param string[]              $supports      Post type supports (title, editor, thumbnail, etc.)
     * @param string[]              $taxonomies    Associated taxonomies
     * @param string|null           $rewriteSlug   Custom rewrite slug (shorthand for rewrite)
     * @param bool                  $hierarchical  Whether the post type is hierarchical (like pages)
     * @param int|null              $menuPosition  Position in the admin menu
     * @param array<string, string> $labels        Custom labels (merged with auto-generated ones)
     * @param array<string, mixed>|false|null $rewrite Full rewrite config, false to disable, or null for default
     */
    public function __construct(
        public string $name,
        public ?string $singular = null,
        public ?string $plural = null,
        public bool $public = true,
        public bool $hasArchive = false,
        public bool $showInRest = true,
        public ?string $menuIcon = null,
        public array $supports = ['title', 'editor', 'thumbnail'],
        public array $taxonomies = [],
        public ?string $rewriteSlug = null,
        public bool $hierarchical = false,
        public ?int $menuPosition = null,
        public array $labels = [],
        public array|false|null $rewrite = null,
    ) {}
}
