<?php

declare(strict_types=1);

namespace Demo\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(
    name: 'testimonial',
    singular: 'Testimonial',
    plural: 'Testimonials',
    public: false,
    showInRest: true,
    menuIcon: 'dashicons-format-quote',
    supports: ['title', 'editor', 'thumbnail', 'custom-fields'],
)]
// The keys the accessors read, declared so each has a type and a REST schema: the
// block editor can edit them, and `rating` comes back as an integer.
#[AsPostMeta(key: 'author_name', type: 'string', description: 'Who said it')]
#[AsPostMeta(key: 'author_role', type: 'string', description: 'Their role')]
#[AsPostMeta(key: 'company', type: 'string', description: 'Where they work')]
#[AsPostMeta(key: 'rating', type: 'integer', default: 5, description: 'Rating out of five')]
final class Testimonial extends Post
{
    public function authorName(): string
    {
        return $this->meta('author_name') ?: $this->title();
    }

    public function authorRole(): ?string
    {
        return $this->meta('author_role');
    }

    public function company(): ?string
    {
        return $this->meta('company');
    }

    /** The highest rating, which the template counts the stars against. */
    public const int MAX_RATING = 5;

    public function rating(): ?int
    {
        $rating = $this->meta('rating');

        return is_numeric($rating) ? max(0, min(self::MAX_RATING, (int) $rating)) : null;
    }
}
