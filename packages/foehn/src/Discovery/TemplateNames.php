<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

/**
 * The template names a request can render, most specific first.
 *
 * A request renders the first name a controller claims, or the last one when no
 * controller claims any (see TemplateControllerMap::resolve()). The names before the
 * last one are used only when a controller claims them: a `single-product-red` view
 * is not looked for unless a controller asks for it.
 *
 * TemplateControllerDiscovery names the current request with them, and
 * `wp foehn inspect` names the sample requests it gives a test URL for, so both
 * agree on the template a URL renders.
 */
final class TemplateNames
{
    /**
     * A single post: `single-{type}-{slug}`, `single-{type}`, `single`.
     *
     * A post of type `post` has no `single-post`: it renders `single`.
     *
     * @return non-empty-list<string>
     */
    public static function single(string $postType, ?string $slug): array
    {
        return [
            ...($slug !== null ? ["single-{$postType}-{$slug}"] : []),
            ...($postType !== 'post' ? ["single-{$postType}"] : []),
            'single',
        ];
    }

    /**
     * A page: `page-{slug}`, `page-{id}`, `page`.
     *
     * @return non-empty-list<string>
     */
    public static function page(?string $slug, ?int $id): array
    {
        return [
            ...($slug !== null ? ["page-{$slug}"] : []),
            ...($id !== null ? ["page-{$id}"] : []),
            'page',
        ];
    }

    /**
     * A category archive: `category-{slug}`, `category`.
     *
     * @return non-empty-list<string>
     */
    public static function category(?string $slug): array
    {
        return [...($slug !== null ? ["category-{$slug}"] : []), 'category'];
    }

    /**
     * A tag archive: `tag-{slug}`, `tag`.
     *
     * @return non-empty-list<string>
     */
    public static function tag(?string $slug): array
    {
        return [...($slug !== null ? ["tag-{$slug}"] : []), 'tag'];
    }

    /**
     * A custom taxonomy archive: `taxonomy-{taxonomy}-{slug}`, `taxonomy-{taxonomy}`,
     * `taxonomy`.
     *
     * @return non-empty-list<string>
     */
    public static function taxonomy(?string $taxonomy, ?string $slug): array
    {
        if ($taxonomy === null) {
            return ['taxonomy'];
        }

        return [
            ...($slug !== null ? ["taxonomy-{$taxonomy}-{$slug}"] : []),
            "taxonomy-{$taxonomy}",
            'taxonomy',
        ];
    }
}
