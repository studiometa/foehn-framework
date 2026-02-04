<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\PostTypes;

use Studiometa\WPTempest\Attributes\AsPostType;

/**
 * Fluent builder for WordPress custom post types.
 */
final class PostTypeBuilder
{
    private string $singular;

    private string $plural;

    private bool $public = true;

    private bool $hasArchive = false;

    private bool $showInRest = true;

    private ?string $menuIcon = null;

    /** @var string[] */
    private array $supports = ['title', 'editor', 'thumbnail'];

    /** @var string[] */
    private array $taxonomies = [];

    private ?string $rewriteSlug = null;

    /** @var array<string, mixed> */
    private array $extraArgs = [];

    public function __construct(
        private readonly string $name,
    ) {
        $this->singular = ucfirst($name);
        $this->plural = ucfirst($name) . 's';
    }

    /**
     * Create a builder from an AsPostType attribute.
     */
    public static function fromAttribute(AsPostType $attribute): self
    {
        $builder = new self($attribute->name);

        $builder->singular = $attribute->singular ?? ucfirst($attribute->name);
        $builder->plural = $attribute->plural ?? ucfirst($attribute->name) . 's';
        $builder->public = $attribute->public;
        $builder->hasArchive = $attribute->hasArchive;
        $builder->showInRest = $attribute->showInRest;
        $builder->menuIcon = $attribute->menuIcon;
        $builder->supports = $attribute->supports;
        $builder->taxonomies = $attribute->taxonomies;
        $builder->rewriteSlug = $attribute->rewriteSlug;

        return $builder;
    }

    public function setLabels(string $singular, string $plural): self
    {
        $this->singular = $singular;
        $this->plural = $plural;

        return $this;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function setHasArchive(bool $hasArchive): self
    {
        $this->hasArchive = $hasArchive;

        return $this;
    }

    public function setShowInRest(bool $showInRest): self
    {
        $this->showInRest = $showInRest;

        return $this;
    }

    public function setMenuIcon(?string $menuIcon): self
    {
        $this->menuIcon = $menuIcon;

        return $this;
    }

    /**
     * @param string[] $supports
     */
    public function setSupports(array $supports): self
    {
        $this->supports = $supports;

        return $this;
    }

    /**
     * @param string[] $taxonomies
     */
    public function setTaxonomies(array $taxonomies): self
    {
        $this->taxonomies = $taxonomies;

        return $this;
    }

    public function setRewriteSlug(?string $slug): self
    {
        $this->rewriteSlug = $slug;

        return $this;
    }

    /**
     * Set additional arguments for register_post_type().
     *
     * @param array<string, mixed> $args
     */
    public function setExtraArgs(array $args): self
    {
        $this->extraArgs = $args;

        return $this;
    }

    /**
     * Merge additional arguments for register_post_type().
     *
     * @param array<string, mixed> $args
     */
    public function mergeExtraArgs(array $args): self
    {
        $this->extraArgs = array_merge($this->extraArgs, $args);

        return $this;
    }

    /**
     * Build the arguments array for register_post_type().
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $labels = [
            'name' => $this->plural,
            'singular_name' => $this->singular,
            'add_new' => 'Add New',
            'add_new_item' => "Add New {$this->singular}",
            'edit_item' => "Edit {$this->singular}",
            'new_item' => "New {$this->singular}",
            'view_item' => "View {$this->singular}",
            'view_items' => "View {$this->plural}",
            'search_items' => "Search {$this->plural}",
            'not_found' => "No {$this->plural} found",
            'not_found_in_trash' => "No {$this->plural} found in Trash",
            'all_items' => "All {$this->plural}",
            'archives' => "{$this->singular} Archives",
            'attributes' => "{$this->singular} Attributes",
            'insert_into_item' => "Insert into {$this->singular}",
            'uploaded_to_this_item' => "Uploaded to this {$this->singular}",
            'filter_items_list' => "Filter {$this->plural} list",
            'items_list_navigation' => "{$this->plural} list navigation",
            'items_list' => "{$this->plural} list",
            'item_published' => "{$this->singular} published.",
            'item_published_privately' => "{$this->singular} published privately.",
            'item_reverted_to_draft' => "{$this->singular} reverted to draft.",
            'item_scheduled' => "{$this->singular} scheduled.",
            'item_updated' => "{$this->singular} updated.",
        ];

        $args = [
            'labels' => $labels,
            'public' => $this->public,
            'has_archive' => $this->hasArchive,
            'show_in_rest' => $this->showInRest,
            'supports' => $this->supports,
            'taxonomies' => $this->taxonomies,
        ];

        if ($this->menuIcon !== null) {
            $args['menu_icon'] = $this->menuIcon;
        }

        if ($this->rewriteSlug !== null) {
            $args['rewrite'] = ['slug' => $this->rewriteSlug];
        }

        return array_merge($args, $this->extraArgs);
    }

    /**
     * Register the post type with WordPress.
     */
    public function register(): \WP_Post_Type|\WP_Error
    {
        return register_post_type($this->name, $this->build());
    }

    public function getName(): string
    {
        return $this->name;
    }
}
