<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Stubs;

use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Tempest\Discovery\SkipDiscovery;
use Timber\Term;

#[SkipDiscovery]
#[AsTaxonomy(
    name: 'dummy-taxonomy',
    postTypes: ['post'],
    singular: 'Dummy Singular',
    plural: 'Dummy Plural',
    public: true,
    hierarchical: false,
    showInRest: true,
    showAdminColumn: true,
)]
final class TaxonomyStub extends Term
{
    // Add custom methods for your taxonomy here
    //
    // Example:
    // public function iconUrl(): string
    // {
    //     return get_field('icon', $this);
    // }
}
