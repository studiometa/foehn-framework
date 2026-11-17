<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function Studiometa\Foehn\breadcrumbs;

/**
 * The breadcrumb trail of the current request, for the theme to write its own markup.
 *
 * ```twig
 * {% set items = breadcrumbs() %}
 * {% if items %}
 *   <nav aria-label="Breadcrumb">
 *     <ol>
 *       {% for item in items %}
 *         <li>
 *           {% if item.url %}
 *             <a href="{{ item.url }}">{{ item.label }}</a>
 *           {% else %}
 *             <span aria-current="page">{{ item.label }}</span>
 *           {% endif %}
 *         </li>
 *       {% endfor %}
 *     </ol>
 *   </nav>
 * {% endif %}
 * ```
 *
 * See {@see breadcrumbs()} for where the items come from.
 */
#[AsTwigExtension]
final class BreadcrumbsExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('breadcrumbs', breadcrumbs(...))];
    }
}
