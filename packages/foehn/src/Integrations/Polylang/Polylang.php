<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Integrations\Polylang;

use Studiometa\Foehn\Contracts\Multilingual;
use WP_Post;
use WP_Term;

/**
 * The {@see Multilingual} contract, answered by Polylang through its `pll_*` functions.
 *
 * Polylang is a plugin of the site, never a dependency of the framework. Free Polylang
 * is enough: Føhn calls only the functions of its public API.
 *
 * @see https://polylang.pro/doc/function-reference/
 */
final readonly class Polylang implements Multilingual
{
    /**
     * Whether Polylang is loaded and has at least one language.
     *
     * Without a language, Polylang does not filter anything, and the site has one
     * language as far as Føhn is concerned.
     */
    public static function isActive(): bool
    {
        return function_exists('pll_languages_list') && pll_languages_list() !== [];
    }

    public function defaultLanguage(): string
    {
        $language = pll_default_language();

        return is_string($language) ? $language : '';
    }

    public function currentLanguage(): string
    {
        $language = pll_current_language();

        return is_string($language) && $language !== '' ? $language : $this->defaultLanguage();
    }

    /**
     * In the admin, Polylang sets its current language to the language of the admin bar
     * filter, and to none when "all languages" is selected.
     */
    public function adminLanguage(): ?string
    {
        $language = pll_current_language();

        return is_string($language) && $language !== '' ? $language : null;
    }

    public function languages(): array
    {
        /** @var list<string> $codes */
        $codes = pll_languages_list(['fields' => 'slug']);
        /** @var list<string> $names */
        $names = pll_languages_list(['fields' => 'name']);

        return array_combine($codes, count($names) === count($codes) ? $names : $codes);
    }

    public function language(WP_Post|WP_Term $object): ?string
    {
        $language = $object instanceof WP_Term
            ? pll_get_term_language($object->term_id)
            : pll_get_post_language($object->ID);

        return is_string($language) && $language !== '' ? $language : null;
    }

    public function translations(WP_Post|WP_Term $object): array
    {
        if ($object instanceof WP_Term) {
            return array_map(intval(...), pll_get_term_translations($object->term_id));
        }

        // Polylang keeps the link to a draft too, but a visitor cannot open it.
        return array_filter(
            array_map(intval(...), pll_get_post_translations($object->ID)),
            static fn(int $id): bool => get_post_status($id) === 'publish',
        );
    }

    public function url(string $language, WP_Post|WP_Term|null $object = null): string
    {
        $translation = $object === null ? null : $this->translations($object)[$language] ?? null;

        if ($translation !== null) {
            $url = $object instanceof WP_Term ? get_term_link($translation) : get_permalink($translation);

            if (is_string($url)) {
                return $url;
            }
        }

        return pll_home_url($language);
    }
}
