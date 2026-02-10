<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsTaxonomy;
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
