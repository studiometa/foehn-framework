<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Query\FacetOption;
use Studiometa\Foehn\Query\Facets;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The counted options of a filter, for the template that draws it.
 *
 * ```twig
 * {% for option in facet('project_category') %}
 *   <label>
 *     <input
 *       type="checkbox"
 *       name="project_category[]"
 *       value="{{ option.term.slug }}"
 *       {{ option.active ? 'checked' }}
 *       {{ option.isEmpty ? 'disabled' }} />
 *     {{ option.term.name }}
 *     {% if option.count is not null %}({{ option.count }}){% endif %}
 *   </label>
 * {% endfor %}
 * ```
 *
 * Each option carries what a filter UI needs and nothing else: the term, whether it is
 * selected, and how many results it would give in the view being looked at. See
 * {@see Facets} for why each facet is counted with its own filter removed, and
 * {@see FacetOption::$count} for why a count can be null.
 */
#[AsTwigExtension]
final class FacetExtension extends AbstractExtension
{
    public function __construct(
        private readonly Facets $facets,
    ) {}

    public function getName(): string
    {
        return 'foehn_facet';
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('facet', $this->facet(...))];
    }

    /**
     * @return list<FacetOption>
     */
    public function facet(string $taxonomy): array
    {
        return $this->facets->for($taxonomy);
    }
}
