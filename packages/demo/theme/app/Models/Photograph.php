<?php

declare(strict_types=1);

namespace Demo\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Data\LinkData;
use Timber\Image;

/**
 * A photograph of the media library, as Timber builds it.
 *
 * #[AsTimberModel] maps an existing post type to a class, here `attachment`, which is
 * not a post type the theme registers. Every attachment Timber builds is then a
 * Photograph: `post.thumbnail`, `Timber::get_post()` and the gallery of a project.
 *
 * The map replaces Timber's own choice between Image and Attachment, so a PDF is a
 * Photograph too. The demo has one PDF, and nothing builds it through Timber.
 */
#[AsTimberModel('attachment')]
final class Photograph extends Image
{
    /**
     * The photographer and the page to credit them with, as MediaSeeder stored them.
     *
     * The Unsplash licence asks that the photographer be named, so the template prints
     * this under every plate.
     */
    public function credit(): ?LinkData
    {
        $name = $this->meta('credit_photographer');

        if (!is_string($name) || $name === '') {
            return null;
        }

        $url = $this->meta('credit_url');

        return new LinkData(url: is_string($url) ? $url : '', title: $name);
    }

    /**
     * Portrait or landscape, which decides the ratio of the plate.
     */
    public function orientation(): string
    {
        return $this->height() > $this->width() ? 'portrait' : 'landscape';
    }
}
