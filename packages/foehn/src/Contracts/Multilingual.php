<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use WP_Post;
use WP_Term;

/**
 * What Føhn asks a multilingual plugin, whichever plugin it is.
 *
 * Languages are named by their code, as the plugin knows them (`en`, `fr`). One
 * adapter for each plugin implements it: Polylang now, WPML later. Føhn turns the
 * adapter on when its plugin is active, and {@see \Studiometa\Foehn\multilingual()}
 * gives it, or null on a site with one language.
 */
interface Multilingual
{
    /**
     * The code of the default language.
     */
    public function defaultLanguage(): string;

    /**
     * The code of the language of the current request on the front end, or the default
     * language when the plugin has not found it yet.
     */
    public function currentLanguage(): string;

    /**
     * The code of the language selected in the admin bar, or null when "all languages"
     * is selected.
     */
    public function adminLanguage(): ?string;

    /**
     * Every language of the site, in the order of the plugin, as code => name.
     *
     * @return array<string, string>
     */
    public function languages(): array;

    /**
     * Whether the posts of a post type have a language and translations. The posts of
     * a post type that is not translated have no language, and show in every language.
     */
    public function isTranslatedPostType(string $postType): bool;

    /**
     * The code of the language of a post or a term, whatever its status, or null when
     * it has none.
     */
    public function language(WP_Post|WP_Term $object): ?string;

    /**
     * The published translations of a post or a term, the object itself included, as
     * code => id.
     *
     * @return array<string, int>
     */
    public function translations(WP_Post|WP_Term $object): array;

    /**
     * The URL of the translation of an object in a language, or the home page of that
     * language when the object has no translation in it or when there is no object.
     */
    public function url(string $language, WP_Post|WP_Term|null $object = null): string;
}
