<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\PostTypes;

use Studiometa\WPTempest\Attributes\AsTaxonomy;

/**
 * Fluent builder for WordPress custom taxonomies.
 */
final class TaxonomyBuilder
{
    private string $singular;

    private string $plural;

    /** @var string[] */
    private array $postTypes = [];

    private bool $public = true;

    private bool $hierarchical = false;

    private bool $showInRest = true;

    private bool $showAdminColumn = true;

    private ?string $rewriteSlug = null;

    /** @var array<string, string> */
    private array $customLabels = [];

    /** @var array<string, mixed>|false|null */
    private array|false|null $rewrite = null;

    /** @var array<string, mixed> */
    private array $extraArgs = [];

    public function __construct(
        private readonly string $name,
    ) {
        $this->singular = ucfirst($name);
        $this->plural = ucfirst($name) . 's';
    }

    /**
     * Create a builder from an AsTaxonomy attribute.
     */
    public static function fromAttribute(AsTaxonomy $attribute): self
    {
        $builder = new self($attribute->name);

        $builder->singular = $attribute->singular ?? ucfirst($attribute->name);
        $builder->plural = $attribute->plural ?? ucfirst($attribute->name) . 's';
        $builder->postTypes = $attribute->postTypes;
        $builder->public = $attribute->public;
        $builder->hierarchical = $attribute->hierarchical;
        $builder->showInRest = $attribute->showInRest;
        $builder->showAdminColumn = $attribute->showAdminColumn;
        $builder->rewriteSlug = $attribute->rewriteSlug;
        $builder->customLabels = $attribute->labels;
        $builder->rewrite = $attribute->rewrite;

        return $builder;
    }

    public function setLabels(string $singular, string $plural): self
    {
        $this->singular = $singular;
        $this->plural = $plural;

        return $this;
    }

    /**
     * Set custom labels (merged with auto-generated ones).
     *
     * @param array<string, string> $labels
     */
    public function setCustomLabels(array $labels): self
    {
        $this->customLabels = $labels;

        return $this;
    }

    /**
     * @param string[] $postTypes
     */
    public function setPostTypes(array $postTypes): self
    {
        $this->postTypes = $postTypes;

        return $this;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function setHierarchical(bool $hierarchical): self
    {
        $this->hierarchical = $hierarchical;

        return $this;
    }

    public function setShowInRest(bool $showInRest): self
    {
        $this->showInRest = $showInRest;

        return $this;
    }

    public function setShowAdminColumn(bool $showAdminColumn): self
    {
        $this->showAdminColumn = $showAdminColumn;

        return $this;
    }

    public function setRewriteSlug(?string $slug): self
    {
        $this->rewriteSlug = $slug;

        return $this;
    }

    /**
     * Set the full rewrite configuration.
     *
     * @param array<string, mixed>|false|null $rewrite
     */
    public function setRewrite(array|false|null $rewrite): self
    {
        $this->rewrite = $rewrite;

        return $this;
    }

    /**
     * Set additional arguments for register_taxonomy().
     *
     * @param array<string, mixed> $args
     */
    public function setExtraArgs(array $args): self
    {
        $this->extraArgs = $args;

        return $this;
    }

    /**
     * Merge additional arguments for register_taxonomy().
     *
     * @param array<string, mixed> $args
     */
    public function mergeExtraArgs(array $args): self
    {
        $this->extraArgs = array_merge($this->extraArgs, $args);

        return $this;
    }

    /**
     * Build the arguments array for register_taxonomy().
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $labels = [
            'name' => $this->plural,
            'singular_name' => $this->singular,
            'search_items' => "Search {$this->plural}",
            'popular_items' => "Popular {$this->plural}",
            'all_items' => "All {$this->plural}",
            'edit_item' => "Edit {$this->singular}",
            'update_item' => "Update {$this->singular}",
            'add_new_item' => "Add New {$this->singular}",
            'new_item_name' => "New {$this->singular} Name",
            'separate_items_with_commas' => "Separate {$this->plural} with commas",
            'add_or_remove_items' => "Add or remove {$this->plural}",
            'choose_from_most_used' => "Choose from the most used {$this->plural}",
            'not_found' => "No {$this->plural} found",
            'no_terms' => "No {$this->plural}",
            'filter_by_item' => "Filter by {$this->singular}",
            'items_list_navigation' => "{$this->plural} list navigation",
            'items_list' => "{$this->plural} list",
            'back_to_items' => "← Back to {$this->plural}",
            'item_link' => "{$this->singular} Link",
            'item_link_description' => "A link to a {$this->singular}",
        ];

        // Add hierarchical-specific labels
        if ($this->hierarchical) {
            $labels['parent_item'] = "Parent {$this->singular}";
            $labels['parent_item_colon'] = "Parent {$this->singular}:";
        }

        // Merge custom labels (overrides auto-generated ones)
        if ($this->customLabels !== []) {
            $labels = array_merge($labels, $this->customLabels);
        }

        $args = [
            'labels' => $labels,
            'public' => $this->public,
            'hierarchical' => $this->hierarchical,
            'show_in_rest' => $this->showInRest,
            'show_admin_column' => $this->showAdminColumn,
        ];

        // Handle rewrite configuration
        // Priority: $rewrite (full config) > $rewriteSlug (shorthand)
        $rewrite = match (true) {
            $this->rewrite !== null => $this->rewrite,
            $this->rewriteSlug !== null => ['slug' => $this->rewriteSlug],
            default => null,
        };

        if ($rewrite !== null) {
            $args['rewrite'] = $rewrite;
        }

        return array_merge($args, $this->extraArgs);
    }

    /**
     * Register the taxonomy with WordPress.
     */
    public function register(): \WP_Taxonomy|\WP_Error
    {
        return register_taxonomy($this->name, $this->postTypes, $this->build());
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getPostTypes(): array
    {
        return $this->postTypes;
    }
}
