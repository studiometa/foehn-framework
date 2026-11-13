<?php

declare(strict_types=1);

namespace Demo\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

#[AsSeeder(order: 40, description: 'Three testimonials')]
final class TestimonialSeeder implements SeederInterface
{
    private const array TESTIMONIALS = [
        'water-journal' => [
            'title' => 'Water Journal',
            'content' => 'She shot four issues for us. The images hold together without ever repeating themselves, which is exactly what you ask of a series.',
            'author_name' => 'Anna Keller',
            'author_role' => 'Art director',
            'company' => 'Water Journal',
            'rating' => 5,
        ],
        'grille' => [
            'title' => 'Grille Editions',
            'content' => 'An impossible brief, met anyway. She argues with the constraints before the shoot, never after.',
            'author_name' => 'Paul Meyer',
            'author_role' => 'Publisher',
            'company' => 'Grille Editions',
            'rating' => 4,
        ],
        'contrejour' => [
            'title' => 'Contrejour Review',
            'content' => 'The faceless portrait became the cover of the issue. We had not commissioned it.',
            'author_name' => 'Léa Martin',
            'author_role' => 'Editor in chief',
            'company' => 'Contrejour Review',
            'rating' => 5,
        ],
    ];

    public function run(): void
    {
        foreach (self::TESTIMONIALS as $slug => $testimonial) {
            Upsert::post(
                'testimonial',
                $slug,
                [
                    'post_title' => $testimonial['title'],
                    'post_content' => $testimonial['content'],
                ],
                [
                    'author_name' => $testimonial['author_name'],
                    'author_role' => $testimonial['author_role'],
                    'company' => $testimonial['company'],
                    'rating' => $testimonial['rating'],
                ],
            );
        }
    }
}
