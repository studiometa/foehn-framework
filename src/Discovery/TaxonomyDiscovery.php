<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Studiometa\WPTempest\Contracts\ConfiguresTaxonomy;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Studiometa\WPTempest\PostTypes\TaxonomyBuilder;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Timber\Term;

/**
 * Discovers classes marked with #[AsTaxonomy] attribute
 * and registers them as WordPress custom taxonomies.
 */
final class TaxonomyDiscovery implements Discovery
{
    use IsDiscovery;
    use CacheableDiscovery;

    /**
     * Discover taxonomy attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
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

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsConfig' => $class->getReflection()->implementsInterface(ConfiguresTaxonomy::class),
        ]);
    }

    /**
     * Apply discovered taxonomies by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getAllItems() as $item) {
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

        // Build from attribute or cached data
        if (isset($item['attribute'])) {
            $builder = TaxonomyBuilder::fromAttribute($item['attribute']);
            $taxonomyName = $item['attribute']->name;
        } else {
            // Cached format - rebuild attribute
            $attribute = new AsTaxonomy(
                name: $item['name'],
                postTypes: $item['postTypes'] ?? [],
                singular: $item['singular'],
                plural: $item['plural'],
                public: $item['public'] ?? true,
                hierarchical: $item['hierarchical'] ?? false,
                showInRest: $item['showInRest'] ?? true,
                showAdminColumn: $item['showAdminColumn'] ?? true,
                rewriteSlug: $item['rewriteSlug'] ?? null,
            );
            $builder = TaxonomyBuilder::fromAttribute($attribute);
            $taxonomyName = $item['name'];
        }

        // Allow class to customize the builder
        if ($implementsConfig) {
            /** @var ConfiguresTaxonomy $className */
            $builder = $className::configureTaxonomy($builder);
        }

        // Register the taxonomy
        $builder->register();

        // Register Timber class map
        $this->registerTimberClassMap($taxonomyName, $className);
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

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsTaxonomy $attribute */
        $attribute = $item['attribute'];

        return [
            'name' => $attribute->name,
            'singular' => $attribute->singular,
            'plural' => $attribute->plural,
            'postTypes' => $attribute->postTypes,
            'public' => $attribute->public,
            'hierarchical' => $attribute->hierarchical,
            'showInRest' => $attribute->showInRest,
            'showAdminColumn' => $attribute->showAdminColumn,
            'rewriteSlug' => $attribute->rewriteSlug,
            'className' => $item['className'],
            'implementsConfig' => $item['implementsConfig'],
        ];
    }
}
