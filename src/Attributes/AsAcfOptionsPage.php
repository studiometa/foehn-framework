<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a class as an ACF Options Page.
 *
 * The class may optionally implement AcfOptionsPageInterface to define fields.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfOptionsPage
{
    /**
     * @param string $pageTitle The title displayed on the options page
     * @param string|null $menuTitle The title displayed in the admin menu (defaults to $pageTitle)
     * @param string|null $menuSlug The URL slug (defaults to sanitized $pageTitle)
     * @param string $capability The capability required to view the page
     * @param int|null $position The position in the menu (null = bottom)
     * @param string|null $parentSlug Parent menu slug (for sub-pages)
     * @param string|null $iconUrl Menu icon (dashicon, URL, or base64 SVG)
     * @param bool $redirect Whether to redirect to the first child page
     * @param string|null $postId Custom post_id for get_field() (defaults to menu_slug)
     * @param bool $autoload Whether to autoload options (better performance)
     * @param string|null $updateButton Custom text for the update button
     * @param string|null $updatedMessage Custom message shown after update
     */
    public function __construct(
        public string $pageTitle,
        public ?string $menuTitle = null,
        public ?string $menuSlug = null,
        public string $capability = 'edit_posts',
        public ?int $position = null,
        public ?string $parentSlug = null,
        public ?string $iconUrl = null,
        public bool $redirect = true,
        public ?string $postId = null,
        public bool $autoload = true,
        public ?string $updateButton = null,
        public ?string $updatedMessage = null,
    ) {}

    /**
     * Get the effective menu slug.
     */
    public function getMenuSlug(): string
    {
        if ($this->menuSlug !== null) {
            return $this->menuSlug;
        }

        return sanitize_title($this->pageTitle);
    }

    /**
     * Get the effective menu title.
     */
    public function getMenuTitle(): string
    {
        return $this->menuTitle ?? $this->pageTitle;
    }

    /**
     * Get the effective post_id for get_field().
     */
    public function getPostId(): string
    {
        if ($this->postId !== null) {
            return $this->postId;
        }

        return $this->getMenuSlug();
    }

    /**
     * Check if this is a sub-page (has a parent).
     */
    public function isSubPage(): bool
    {
        return $this->parentSlug !== null;
    }
}
