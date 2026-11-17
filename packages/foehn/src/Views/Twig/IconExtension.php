<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Icons\ThemeIcons;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Prints a registered SVG icon, from a template.
 *
 * ```twig
 * {{ icon('arrow-right') }}
 * {{ icon('arrow-right', { size: 16, class: 'button__icon' }) }}
 * {{ icon('core/search', { label: 'Search' }) }}
 * ```
 *
 * A name without a collection is one of the theme's icons, see {@see ThemeIcons}.
 * The markup comes from `wp_get_icon()`, which WordPress sanitized: an unknown icon
 * prints nothing. Before WordPress 7.1 every icon prints nothing, and a notice says
 * so once per request when debug is on.
 */
#[AsTwigExtension]
final class IconExtension extends AbstractExtension
{
    private bool $reported = false;

    public function __construct(
        private readonly FoehnConfig $config,
    ) {}

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('icon', $this->icon(...), ['is_safe' => ['html']])];
    }

    /**
     * @param array{size?: int|null, class?: string, label?: string} $args
     */
    public function icon(string $name, array $args = []): string
    {
        if (!function_exists('wp_get_icon')) {
            $this->reportMissingApi();

            return '';
        }

        return wp_get_icon(ThemeIcons::qualify($name), $args);
    }

    private function reportMissingApi(): void
    {
        if ($this->reported || !$this->config->isDebugEnabled()) {
            return;
        }

        $this->reported = true;

        trigger_error('[Foehn] icon() prints nothing: this WordPress has no Icons API (WordPress 7.1).', E_USER_NOTICE);
    }
}
