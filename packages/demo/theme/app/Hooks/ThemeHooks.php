<?php

declare(strict_types=1);

namespace Demo\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]);
        add_theme_support('responsive-embeds');
        add_theme_support('wp-block-styles');
        add_theme_support('editor-styles');
    }

    /**
     * The projects index is a curated sequence, so it follows menu_order rather than
     * the date a series happened to be published.
     *
     * Defaults, not overrides. Setting these unconditionally is what made the archive's
     * own sort and per-page controls do nothing at all: the form put `orderby` in the
     * URL, WordPress read it, and this handler overwrote it a moment later — a control
     * that renders, submits, changes the address bar and has no effect.
     *
     * At priority 20 so `QueryFiltersHook` has already run: it empties a `posts_per_page`
     * outside the allowlist, and this then supplies the default rather than leaving
     * WordPress to fall back to the site option.
     */
    #[AsAction('pre_get_posts', priority: 20)]
    public function orderProjects(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('project')) {
            return;
        }

        if (!$query->get('orderby')) {
            $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
        }

        if (!$query->get('posts_per_page')) {
            $query->set('posts_per_page', 3);
        }
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }

    #[AsFilter('excerpt_more')]
    public function excerptMore(): string
    {
        return '…';
    }
}
