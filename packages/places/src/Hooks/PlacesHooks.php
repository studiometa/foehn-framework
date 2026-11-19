<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Places\Admin\PlaceMetaBox;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Places\Search\PlaceSearch;
use WP_Query;

/**
 * Every WordPress hook of the place locator, each on the hook that owns its API.
 */
final readonly class PlacesHooks
{
    /** The package templates. */
    public const string TEMPLATES = __DIR__ . '/../../templates';

    public function __construct(
        private PlacesConfig $config,
        private PlaceSearch $search,
        private PlaceMetaBox $metaBox,
    ) {}

    /**
     * Register the contact keys, when the configuration turns them on.
     */
    #[AsAction('init')]
    public function registerContactMeta(): void
    {
        if (!$this->config->contactFields) {
            return;
        }

        $sanitizers = ['phone' => 'sanitize_text_field', 'email' => 'sanitize_email', 'website' => 'esc_url_raw'];

        foreach ($sanitizers as $key => $sanitizer) {
            register_meta('post', $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'object_subtype' => Place::POST_TYPE,
                'sanitize_callback' => $sanitizer,
                'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
            ]);
        }
    }

    #[AsAction('add_meta_boxes_place')]
    public function addMetaBox(): void
    {
        $this->metaBox->add();
    }

    #[AsAction('save_post_place')]
    public function saveMetaBox(int $postId): void
    {
        // The nonce is checked in PlaceMetaBox::save().
        $this->metaBox->save($postId, $_POST);
    }

    /**
     * The config token wins over the option.
     */
    #[AsFilter('pre_option_foehn_places_mapbox_access_token')]
    public function tokenOption(mixed $value): mixed
    {
        return $this->config->accessToken ?? $value;
    }

    /**
     * The config style wins over the option.
     */
    #[AsFilter('pre_option_foehn_places_mapbox_style')]
    public function styleOption(mixed $value): mixed
    {
        return $this->config->style ?? $value;
    }

    /**
     * Keep the stored token when the config sets it: the read-only field posts the config value.
     */
    #[AsFilter('pre_update_option_foehn_places_mapbox_access_token', acceptedArgs: 2)]
    public function keepToken(mixed $value, mixed $old): mixed
    {
        return $this->config->accessToken === null ? $value : $old;
    }

    /**
     * Keep the stored style when the config sets it.
     */
    #[AsFilter('pre_update_option_foehn_places_mapbox_style', acceptedArgs: 2)]
    public function keepStyle(mixed $value, mixed $old): mixed
    {
        return $this->config->style === null ? $value : $old;
    }

    /**
     * Parse the search before WordPress parses the main query.
     *
     * An invalid request drops the facet query vars, so an unknown term never turns
     * the archive into a `404` taxonomy view: the request answers `400` instead.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    #[AsFilter('request')]
    public function parseRequest(array $vars): array
    {
        if (!self::isArchiveRequest($vars)) {
            return $vars;
        }

        $request = $this->search->parse($_SERVER['QUERY_STRING'] ?? '');

        foreach ($this->search->facetTaxonomies() as $taxonomy) {
            unset($vars[$taxonomy]);

            $slugs = $request?->terms[$taxonomy] ?? [];

            if ($slugs !== []) {
                $vars[$taxonomy] = implode(',', $slugs);
            }
        }

        return $vars;
    }

    /**
     * Narrow the main query of the place archive to the eligible places.
     */
    #[AsAction('pre_get_posts')]
    public function narrow(WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive(Place::POST_TYPE)) {
            return;
        }

        $this->search->narrow($query);
    }

    /**
     * Answer an invalid search with `400`.
     *
     * On `template_redirect`, after `WP::handle_404()`, which would reset the status.
     * Section requests keep it too.
     */
    #[AsAction('template_redirect')]
    public function sendStatus(): void
    {
        if ($this->search->isInvalid() && is_post_type_archive(Place::POST_TYPE)) {
            status_header(400);
            nocache_headers();
        }
    }

    /**
     * Whether the query vars are those of the place archive (not a single place).
     *
     * @param array<string, mixed> $vars
     */
    public static function isArchiveRequest(array $vars): bool
    {
        return (
            ($vars['post_type'] ?? null) === Place::POST_TYPE
            && ($vars[Place::POST_TYPE] ?? null) === null
            && ($vars['name'] ?? null) === null
            && ($vars['p'] ?? null) === null
        );
    }

    /**
     * Register the `@places` and `@places-pkg` Twig namespaces.
     *
     * @param array<int|string, list<string>> $locations
     * @return array<int|string, list<string>>
     */
    #[AsFilter('timber/locations')]
    public function templateLocations(array $locations): array
    {
        return self::locations($locations, get_stylesheet_directory(), get_template_directory());
    }

    /**
     * `@places` looks in `templates/places/` of the child theme, then of the parent
     * theme, then in the package. `@places-pkg` is the package alone, so an override
     * can extend or include the template it replaces.
     *
     * A theme directory that does not exist is left out: Twig refuses a missing path.
     *
     * @param array<int|string, list<string>> $locations
     * @return array<int|string, list<string>>
     */
    public static function locations(array $locations, string $stylesheetDirectory, string $templateDirectory): array
    {
        $paths = [];

        foreach (array_unique([$stylesheetDirectory, $templateDirectory]) as $directory) {
            $path = rtrim($directory, '/') . '/templates/places';

            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        $locations['places'] = [...$paths, self::TEMPLATES];
        $locations['places-pkg'] = [self::TEMPLATES];

        return $locations;
    }
}
