<?php

declare(strict_types=1);

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(
    name: 'testimonial',
    singular: 'Témoignage',
    plural: 'Témoignages',
    public: false,
    showInRest: true,
    menuIcon: 'dashicons-format-quote',
    supports: ['title', 'editor', 'thumbnail'],
)]
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

    public function rating(): ?int
    {
        $rating = $this->meta('rating');

        return $rating ? (int) $rating : null;
    }
}
