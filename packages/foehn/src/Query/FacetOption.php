<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Query;

use WP_Term;

/**
 * One choice in a facet: a term, whether it is selected, and what choosing it gives.
 *
 * A plain `WP_Term` rather than a `Timber\Term`, so nothing here depends on the view
 * layer. `name`, `slug` and `term_id` are what a filter UI reads, and a template that
 * wants a Timber term can ask for one by id.
 */
final readonly class FacetOption
{
    public function __construct(
        public WP_Term $term,
        /**
         * How many posts in the current view carry this term, or null when it was not
         * counted.
         *
         * Null is not zero. Counting means one query over the filtered set, and
         * {@see Facets::MAX_COUNTED_POSTS} is where this stops paying for it — a
         * template showing "(null)" as nothing at all is right, and one showing it as
         * "(0)" would be a lie that hides every option.
         */
        public ?int $count,
        /** Whether this term is one of the values the current view is filtered by. */
        public bool $active,
    ) {}

    /**
     * Whether choosing this option would give an empty page.
     *
     * A facet's job is to say so before the visitor finds out. An uncounted option is
     * never reported as a dead end, because nothing checked.
     */
    public function isEmpty(): bool
    {
        return $this->count === 0;
    }
}
