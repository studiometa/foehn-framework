<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search;

use WP_Post;

use function Studiometa\Foehn\multilingual;

/**
 * The languages of the indexes: one index for each language of the site.
 *
 * The codes come from the {@see \Studiometa\Foehn\Contracts\Multilingual} contract
 * when a multilingual plugin is active, else from the locale of the site.
 */
final class Languages
{
    /**
     * Every language of the site.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $multilingual = multilingual();

        return $multilingual === null ? [$this->siteLanguage()] : array_keys($multilingual->languages());
    }

    /**
     * The language of the current request.
     */
    public function current(): string
    {
        return multilingual()?->currentLanguage() ?? $this->siteLanguage();
    }

    /**
     * The languages whose index holds a post: its own language, every language when the
     * multilingual plugin does not translate its post type, none when a translated post
     * has no language.
     *
     * @return list<string>
     */
    public function of(WP_Post $post): array
    {
        $multilingual = multilingual();

        if ($multilingual === null) {
            return [$this->siteLanguage()];
        }

        if (!$multilingual->isTranslatedPostType($post->post_type)) {
            return $this->all();
        }

        $language = $multilingual->language($post);

        return $language !== null && $this->has($language) ? [$language] : [];
    }

    /**
     * Whether a code is a language of the site.
     */
    public function has(string $language): bool
    {
        return in_array($language, $this->all(), true);
    }

    /**
     * The ISO 639-1 code Loupe stems and tokenizes with: `fr` for `fr`, `fr-be` or `fr_FR`.
     */
    public static function isoCode(string $language): string
    {
        return strtolower(substr($language, 0, 2));
    }

    private function siteLanguage(): string
    {
        return self::isoCode(get_locale());
    }
}
