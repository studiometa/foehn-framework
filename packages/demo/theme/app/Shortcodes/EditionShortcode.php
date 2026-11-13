<?php

declare(strict_types=1);

namespace Demo\Shortcodes;

use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

/**
 * `[edition size="15" paper="baryta" format="40x50 cm"]` — the terms of a print edition.
 *
 * #[AsShortcode] goes on the method that renders it. The class is built by the
 * container, so the method renders a Twig view rather than building HTML in PHP.
 */
final readonly class EditionShortcode
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    /**
     * @param array<string, string> $atts
     */
    #[AsShortcode('edition')]
    public function render(array $atts, ?string $content = null): string
    {
        $atts = [...['size' => '15', 'paper' => 'baryta', 'format' => ''], ...$atts];

        return $this->view->render('shortcodes/edition', [
            'size' => max(1, (int) $atts['size']),
            'paper' => (string) $atts['paper'],
            'format' => (string) $atts['format'],
        ]);
    }
}
