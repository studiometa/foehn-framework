<?php

declare(strict_types=1);

namespace Demo\Patterns;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;
use Studiometa\Foehn\Settings\Settings;

/**
 * "Commission a series" — a block pattern built in Twig.
 *
 * The template writes block markup with wp_block_start(), wp_block() and
 * wp_block_end(): a section holding a heading, a paragraph and a callout. An author
 * inserts it from the Patterns tab and edits it like any other blocks. The About page
 * is seeded from it, so the page shows what the pattern gives.
 *
 * The pattern renders once, when it is registered, so the contact address it prints
 * is the one saved at that time.
 */
#[AsBlockPattern(
    name: 'theme/commission',
    title: 'Commission a series',
    categories: ['call-to-action'],
    keywords: ['commission', 'contact', 'callout'],
    description: 'A section that asks for a commission, with a callout giving the contact address.',
)]
final readonly class CommissionPattern implements BlockPatternInterface
{
    /**
     * @return array<string, mixed>
     */
    public function compose(): array
    {
        return [
            'email' => Settings::get('demo_contact_email'),
        ];
    }
}
