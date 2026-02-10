<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\PostTypes\TaxonomyBuilder;

/**
 * Interface for taxonomy classes that need custom configuration.
 *
 * Implement this interface on your Timber\Term model class to customize
 * the taxonomy registration beyond what the #[AsTaxonomy] attribute provides.
 */
interface ConfiguresTaxonomy
{
    /**
     * Configure the taxonomy builder with custom settings.
     *
     * This method is called after the attribute settings are applied,
     * allowing you to override or extend the configuration.
     *
     * @param TaxonomyBuilder $builder The builder pre-configured from the attribute
     * @return TaxonomyBuilder The modified builder
     */
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder;
}
