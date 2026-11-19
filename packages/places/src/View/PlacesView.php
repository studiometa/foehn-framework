<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\View;

use Studiometa\Foehn\Places\Admin\PlacesSettings;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Places\Search\PlaceSearchRequest;
use Studiometa\Foehn\Places\Search\PlaceSearchResult;
use WP_Rewrite;

use function Studiometa\Foehn\multilingual;

/**
 * What the place templates need besides the search result: the configuration, the
 * resolved Mapbox settings, the section names, and the URLs that keep the request.
 *
 * The theme controller passes it to the page template as `places`, next to
 * `result`, and every package template receives both.
 */
final readonly class PlacesView
{
    /** The four sections a filter, a page link or a position change updates together. */
    public const string SECTIONS = 'place-filters,place-summary,place-results,place-pagination';

    /** How a map frames the result: on the first load and after each Fetch update. */
    public const array FIT_BOUNDS_OPTIONS = ['padding' => 60, 'maxZoom' => 12];

    public function __construct(
        public PlacesConfig $config,
    ) {}

    public function sections(): string
    {
        return self::SECTIONS;
    }

    /**
     * The Mapbox token: the config value, else the settings page, else empty.
     */
    public function accessToken(): string
    {
        return PlacesSettings::accessToken($this->config);
    }

    public function style(): string
    {
        return PlacesSettings::style($this->config);
    }

    public function archiveUrl(): string
    {
        $url = get_post_type_archive_link(Place::POST_TYPE);

        return is_string($url) && $url !== '' ? $url : home_url('/');
    }

    /**
     * The archive URL of a request, with changes, on a page of the list.
     *
     * A null or empty change removes the argument. `view=list` is the default, so it is
     * left out, and every URL of one state is the same URL, which the page cache keys once.
     *
     * @param array<string, string|int|null> $changes
     */
    public function url(PlaceSearchRequest $request, array $changes = [], int $page = 1): string
    {
        $args = [...$request->toQueryArgs(), ...$changes];

        if (($args['view'] ?? null) === 'list') {
            unset($args['view']);
        }

        $query = [];

        foreach ($args as $name => $value) {
            if (!($value !== null && $value !== '')) {
                continue;
            }

            $query[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
        }

        $url = $this->pageUrl($page);

        return $query === [] ? $url : $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $query);
    }

    /**
     * The geocoder options: the language of the page, then the configured options,
     * whose `language` wins.
     *
     * @return array<string, mixed>
     */
    public function geocoderOptions(): array
    {
        return [...['language' => $this->language()], ...$this->config->geocoderOptions];
    }

    /**
     * The `mapOptions` of the MapboxMap: the style, and the bounds to frame on the first load.
     *
     * @return array<string, mixed>
     */
    public function mapOptions(PlaceSearchResult $result): array
    {
        $options = ['style' => $this->style()];

        if ($result->bounds !== null) {
            $options['bounds'] = $result->bounds;
            $options['fitBoundsOptions'] = self::FIT_BOUNDS_OPTIONS;
        }

        return $options;
    }

    /**
     * The plural label of a facet taxonomy.
     */
    public function taxonomyLabel(string $taxonomy): string
    {
        $object = get_taxonomy($taxonomy);
        $labels = is_object($object) ? $object->labels ?? null : null;
        $label = is_object($labels) ? $labels->name ?? null : null;

        return is_string($label) && $label !== '' ? $label : $taxonomy;
    }

    /**
     * A distance in metres, as a visitor reads it: `850 m`, `2.4 km`, `36 km`.
     */
    public function distance(float $metres): string
    {
        if ($metres < 1000) {
            return sprintf('%d m', (int) (round($metres / 10) * 10));
        }

        $kilometres = $metres / 1000;

        return $kilometres < 10 ? sprintf('%.1f km', $kilometres) : sprintf('%d km', (int) round($kilometres));
    }

    private function pageUrl(int $page): string
    {
        $archive = $this->archiveUrl();

        if ($page <= 1) {
            return $archive;
        }

        // @mago-expect lint:no-global
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;

        if ($rewrite instanceof WP_Rewrite && $rewrite->using_permalinks()) {
            return user_trailingslashit(trailingslashit($archive) . $rewrite->pagination_base . '/' . $page, 'paged');
        }

        return add_query_arg('paged', (string) $page, $archive);
    }

    private function language(): string
    {
        return multilingual()?->currentLanguage() ?? strtolower(substr(get_locale(), 0, 2));
    }
}
