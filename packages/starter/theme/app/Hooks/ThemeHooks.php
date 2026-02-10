<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu('header', 'Menu principal')]
#[AsMenu('footer', 'Menu footer')]
#[AsMenu('legal', 'Mentions légales')]
#[AsImageSize('card', 400, 300, true)]
#[AsImageSize('hero', 1920, 1080, true)]
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
