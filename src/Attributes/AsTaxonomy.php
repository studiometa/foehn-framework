<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTaxonomy
{
    /**
     * @param string                $name            Taxonomy name (slug)
     * @param string[]              $postTypes       Associated post types
     * @param string|null           $singular        Singular label
     * @param string|null           $plural          Plural label
     * @param bool                  $public          Whether the taxonomy is public
     * @param bool                  $hierarchical    Whether the taxonomy is hierarchical (like categories)
     * @param bool                  $showInRest      Whether to expose in REST API
     * @param bool                  $showAdminColumn Whether to show in admin list column
     * @param string|null           $rewriteSlug     Custom rewrite slug (shorthand for rewrite)
     * @param array<string, string> $labels          Custom labels (merged with auto-generated ones)
     * @param array<string, mixed>|false|null $rewrite Full rewrite config, false to disable, or null for default
     */
    public function __construct(
        public string $name,
        public array $postTypes = [],
        public ?string $singular = null,
        public ?string $plural = null,
        public bool $public = true,
        public bool $hierarchical = false,
        public bool $showInRest = true,
        public bool $showAdminColumn = true,
        public ?string $rewriteSlug = null,
        public array $labels = [],
        public array|false|null $rewrite = null,
    ) {}
}
