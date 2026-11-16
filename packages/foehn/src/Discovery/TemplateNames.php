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
     * A single post: its page template, `single-{type}-{slug}`, `single-{type}`, `single`.
     *
     * A post of type `post` has no `single-post`: it renders `single`.
     *
     * @param string|null $template The page template the post selects, as
     *                              `get_page_template_slug()` gives it.
     * @return non-empty-list<string>
     */
    public static function single(string $postType, ?string $slug, ?string $template): array
    {
        return [
            ...self::pageTemplate($template),
            ...($slug !== null ? ["single-{$postType}-{$slug}"] : []),
            ...($postType !== 'post' ? ["single-{$postType}"] : []),
            'single',
        ];
    }

    /**
     * A page: its page template, `page-{slug}`, `page-{id}`, `page`.
     *
     * @param string|null $template The page template the page selects, as
     *                              `get_page_template_slug()` gives it.
     * @return non-empty-list<string>
     */
    public static function page(?string $slug, ?int $id, ?string $template): array
    {
        return [
            ...self::pageTemplate($template),
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

    /**
     * The name of a page template: its file without `.php`, such as `page-full-width`
     * for `page-full-width.php`. WordPress looks for that file first, before the
     * slug, so it comes first here too. Nothing for the default template.
     *
     * @return list<string>
     */
    private static function pageTemplate(?string $template): array
    {
        if ($template === null || $template === '' || $template === 'default') {
            return [];
        }

        return [str_ends_with($template, '.php') ? substr($template, 0, -4) : $template];
    }
}
