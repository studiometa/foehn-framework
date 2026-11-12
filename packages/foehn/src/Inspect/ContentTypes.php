<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;

/**
 * Post types and taxonomies, joined to their models, their meta fields and each other.
 *
 * Either side of a post type to taxonomy link can declare it, and a model can map a
 * type that WordPress registers, so no single discovery holds the whole answer.
 */
final readonly class ContentTypes
{
    public function __construct(
        private DiscoveredItems $items,
    ) {}

    /**
     * Post types Føhn registers, and the ones it does not register but the project
     * names: a #[AsTimberModel] maps it, or a #[AsPostMeta] gives it fields.
     *
     * A post type's taxonomies are the ones it names plus every #[AsTaxonomy] that
     * names it: either side can declare the link.
     *
     * @return list<array<string, mixed>>
     */
    public function postTypes(): array
    {
        $rows = [];

        foreach ($this->items->of(PostTypeDiscovery::class) as $item) {
            /** @var AsPostType $attribute */
            $attribute = $item['attribute'];

            $rows[$attribute->name] = [
                'name' => $attribute->name,
                'model' => $item['className'],
                'declared' => true,
                'singular' => $attribute->singular,
                'plural' => $attribute->plural,
                'public' => $attribute->public,
                'hierarchical' => $attribute->hierarchical,
                'hasArchive' => $attribute->hasArchive,
                'showInRest' => $attribute->showInRest,
                'rewriteSlug' => $attribute->rewriteSlug,
                'supports' => $attribute->supports,
                'taxonomies' => $attribute->taxonomies,
            ];
        }

        // A type registered outside Føhn has the same keys, so a reader never tests for
        // one: what only the registering code knows is null.
        foreach ($this->namedTypes('post') as $name => $className) {
            $rows[$name] ??= [
                'name' => $name,
                'model' => $className,
                'declared' => false,
                'singular' => null,
                'plural' => null,
                'public' => null,
                'hierarchical' => null,
                'hasArchive' => null,
                'showInRest' => null,
                'rewriteSlug' => null,
                'supports' => null,
                'taxonomies' => [],
            ];
        }

        foreach ($this->items->of(TaxonomyDiscovery::class) as $item) {
            /** @var AsTaxonomy $taxonomy */
            $taxonomy = $item['attribute'];

            foreach ($taxonomy->postTypes as $postType) {
                if (!array_key_exists($postType, $rows)) {
                    continue;
                }

                $rows[$postType]['taxonomies'][] = $taxonomy->name;
            }
        }

        foreach ($rows as $name => $row) {
            $rows[$name]['taxonomies'] = Rows::unique($row['taxonomies']);
            $rows[$name]['meta'] = $this->meta('post', $name);
        }

        return Rows::sorted($rows);
    }

    /**
     * Taxonomies Føhn registers, and the ones it does not register but the project
     * names: a #[AsTimberModel] maps it, or a #[AsPostMeta] gives it fields.
     *
     * @return list<array<string, mixed>>
     */
    public function taxonomies(): array
    {
        $rows = [];

        foreach ($this->items->of(TaxonomyDiscovery::class) as $item) {
            /** @var AsTaxonomy $attribute */
            $attribute = $item['attribute'];

            $rows[$attribute->name] = [
                'name' => $attribute->name,
                'model' => $item['className'],
                'declared' => true,
                'singular' => $attribute->singular,
                'plural' => $attribute->plural,
                'public' => $attribute->public,
                'hierarchical' => $attribute->hierarchical,
                'showInRest' => $attribute->showInRest,
                'rewriteSlug' => $attribute->rewriteSlug,
                'postTypes' => $attribute->postTypes,
            ];
        }

        foreach ($this->namedTypes('term') as $name => $className) {
            $rows[$name] ??= [
                'name' => $name,
                'model' => $className,
                'declared' => false,
                'singular' => null,
                'plural' => null,
                'public' => null,
                'hierarchical' => null,
                'showInRest' => null,
                'rewriteSlug' => null,
                'postTypes' => [],
            ];
        }

        foreach ($this->items->of(PostTypeDiscovery::class) as $item) {
            /** @var AsPostType $postType */
            $postType = $item['attribute'];

            foreach ($postType->taxonomies as $taxonomy) {
                if (!array_key_exists($taxonomy, $rows)) {
                    continue;
                }

                $rows[$taxonomy]['postTypes'][] = $postType->name;
            }
        }

        foreach ($rows as $name => $row) {
            $rows[$name]['postTypes'] = Rows::unique($row['postTypes']);
            $rows[$name]['meta'] = $this->meta('term', $name);
        }

        return Rows::sorted($rows);
    }

    /**
     * The types of one object type the project names, by type name: the
     * #[AsTimberModel] class that maps each one, or null when only a #[AsPostMeta]
     * subtype names it.
     *
     * @param 'post'|'term' $type
     * @return array<string, ?string>
     */
    private function namedTypes(string $type): array
    {
        $types = [];

        foreach ($this->items->of(PostMetaDiscovery::class) as $item) {
            /** @var AsPostMeta $attribute */
            $attribute = $item['attribute'];
            $subtype = (string) $item['objectSubtype'];

            if ($attribute->objectType === $type && $subtype !== '') {
                $types[$subtype] = null;
            }
        }

        foreach ($this->items->of(TimberModelDiscovery::class) as $item) {
            if ($item['type'] !== $type) {
                continue;
            }

            /** @var AsTimberModel $attribute */
            $attribute = $item['attribute'];

            $types[$attribute->name] = (string) $item['className'];
        }

        return $types;
    }

    /**
     * The #[AsPostMeta] fields registered for one post type or taxonomy.
     *
     * A field whose subtype discovery could not resolve is registered without one,
     * and so applies to every object of its type: it is listed on each of them.
     *
     * @param 'post'|'term' $objectType
     * @return list<array<string, mixed>>
     */
    private function meta(string $objectType, string $subtype): array
    {
        $fields = [];

        foreach ($this->items->of(PostMetaDiscovery::class) as $item) {
            /** @var AsPostMeta $attribute */
            $attribute = $item['attribute'];

            if ($attribute->objectType !== $objectType) {
                continue;
            }

            $itemSubtype = (string) $item['objectSubtype'];

            if ($itemSubtype !== '' && $itemSubtype !== $subtype) {
                continue;
            }

            $fields[] = [
                'key' => $attribute->key,
                'type' => $attribute->type,
                'single' => $attribute->single,
                'showInRest' => $attribute->showInRest,
                'default' => $attribute->default,
                'description' => $attribute->description,
            ];
        }

        usort($fields, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));

        return $fields;
    }
}
