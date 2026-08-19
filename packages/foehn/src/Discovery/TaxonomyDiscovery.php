<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

/**
 * Discovers classes marked with #[AsTaxonomy] attribute
 * and registers them as WordPress custom taxonomies.
 */
final class TaxonomyDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover taxonomy attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsTaxonomy::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class extends Timber\Term
        if (!$class->isSubclassOf(Term::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must extend %s to use #[AsTaxonomy]',
                $class->getName(),
                Term::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsConfig' => $class->implementsInterface(ConfiguresTaxonomy::class),
        ]);
    }

    /**
     * Apply discovered taxonomies by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerTaxonomy($item);
        }
    }

    /**
     * Register a single taxonomy with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerTaxonomy(array $item): void
    {
        $className = $item['className'];
        $implementsConfig = $item['implementsConfig'];
        /** @var AsTaxonomy $attribute */
        $attribute = $item['attribute'];
        $builder = TaxonomyBuilder::fromAttribute($attribute);

        // Allow class to customize the builder
        if ($implementsConfig) {
            /** @var ConfiguresTaxonomy $className */
            $builder = $className::configureTaxonomy($builder);
        }

        // Register the taxonomy
        $builder->register();

        // Register Timber class map
        $this->registerTimberClassMap($attribute->name, $className);
    }

    /**
     * Register the Timber class map for this taxonomy.
     *
     * @param string $taxonomy
     * @param class-string $className
     */
    private function registerTimberClassMap(string $taxonomy, string $className): void
    {
        add_filter('timber/term/classmap', static function (array $map) use ($taxonomy, $className): array {
            $map[$taxonomy] = $className;

            return $map;
        });
    }
}
