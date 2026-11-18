<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function Studiometa\Foehn\languages;

/**
 * The language switcher of the current request, for the theme to write its own markup.
 *
 * ```twig
 * {% set items = languages() %}
 * {% if items %}
 *   <ul>
 *     {% for item in items %}
 *       <li>
 *         <a href="{{ item.url }}" hreflang="{{ item.code }}"{% if item.current %} aria-current="true"{% endif %}>
 *           {{ item.name }}
 *         </a>
 *       </li>
 *     {% endfor %}
 *   </ul>
 * {% endif %}
 * ```
 *
 * See {@see languages()} for what each item holds.
 */
#[AsTwigExtension]
final class LanguagesExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('languages', languages(...))];
    }
}
