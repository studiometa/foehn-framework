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
     */
    #[AsAction('pre_get_posts')]
    public function orderProjects(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('project')) {
            return;
        }

        $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
        $query->set('posts_per_page', 3);
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
