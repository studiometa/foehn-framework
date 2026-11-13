<?php

declare(strict_types=1);

namespace Demo\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * The theme's own Twig filters.
 *
 * #[AsTwigExtension] is the whole registration: discovery finds the class, the
 * container builds it, and Føhn adds it to Timber's Twig environment.
 */
#[AsTwigExtension]
final class PortfolioExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [new TwigFilter('plural', $this->plural(...))];
    }

    /**
     * A count with its noun: `{{ 5|plural('image') }}` is "5 images", and
     * `{{ 1|plural('series', 'series') }}` is "1 series".
     */
    public function plural(int|float|string $count, string $singular, ?string $plural = null): string
    {
        $count = (int) $count;

        return sprintf('%d %s', $count, $count === 1 ? $singular : $plural ?? $singular . 's');
    }
}
