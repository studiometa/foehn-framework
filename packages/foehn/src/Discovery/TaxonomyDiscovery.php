<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Timber\Term;

/**
 * Discovers classes marked with #[AsTaxonomy] attribute
 * and registers them as WordPress custom taxonomies.
 */
final class TaxonomyDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover taxonomy attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsTaxonomy::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class extends Timber\Term
        if (!$class->getReflection()->isSubclassOf(Term::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must extend %s to use #[AsTaxonomy]',
                $class->getName(),
                Term::class,
            ));
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsConfig' => $class->implements(ConfiguresTaxonomy::class),
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
