<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Admin;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Places\ValueObjects\PlaceCoordinates;
use Studiometa\Foehn\PostTypes\PostMetaBox;
use Studiometa\Foehn\Settings\SettingField;
use WP_Post;

use function Studiometa\Foehn\multilingual;

/**
 * The "Place" meta box: address search, location, address and contact.
 *
 * Its fields are the native fields of the #[AsPostMeta] meta box and of the settings
 * pages (SettingField). A project field declared with #[AsPostMeta] on a Place
 * subclass is edited in the "Fields" meta box of Føhn, not here.
 */
final readonly class PlaceMetaBox
{
    public const string ID = 'foehn-places-place';

    public const string NONCE = 'foehn_places_nonce';

    /** The request field that holds the values, as key => value. */
    public const string FIELD = 'foehn_places';

    /** The transient that keeps the invalid coordinates error until the next render. */
    public const string ERROR = 'foehn_places_invalid_coordinates_';

    public function __construct(
        private PlacesConfig $config,
    ) {}

    /**
     * The fields of each group, as AsPostMeta declarations that PostMetaBox::setting() reads.
     *
     * @return array<string, list<AsPostMeta>>
     */
    public function groups(): array
    {
        $groups = [
            'Location' => [
                new AsPostMeta(key: 'latitude', type: 'number', control: 'number', label: 'Latitude'),
                new AsPostMeta(key: 'longitude', type: 'number', control: 'number', label: 'Longitude'),
            ],
            'Address' => [
                new AsPostMeta(key: 'street', control: 'text', label: 'Street'),
                new AsPostMeta(key: 'postal_code', control: 'text', label: 'Postal code'),
                new AsPostMeta(key: 'city', control: 'text', label: 'City'),
                new AsPostMeta(key: 'country', control: 'text', label: 'Country'),
            ],
        ];

        if ($this->config->contactFields) {
            $groups['Contact'] = [
                new AsPostMeta(key: 'phone', control: 'tel', label: 'Phone'),
                new AsPostMeta(key: 'email', control: 'email', label: 'Email'),
                new AsPostMeta(key: 'website', control: 'url', label: 'Website'),
            ];
        }

        return $groups;
    }

    /**
     * Add the meta box to the place edit screen.
     */
    public function add(): void
    {
        add_meta_box(self::ID, $this->config->singular, $this->render(...), Place::POST_TYPE, 'normal', 'high');
    }

    /**
     * Print the nonce, the address search and a table for each group.
     */
    public function render(WP_Post $post): void
    {
        wp_nonce_field(self::ID, self::NONCE);

        $error = get_transient(self::ERROR . $post->ID . '_' . get_current_user_id());

        if (is_string($error) && $error !== '') {
            delete_transient(self::ERROR . $post->ID . '_' . get_current_user_id());
            printf('<div class="notice notice-error inline"><p>%s</p></div>', esc_html($error));
        }

        echo $this->geocoder();

        foreach ($this->groups() as $title => $fields) {
            printf('<h3>%s</h3><table class="form-table" role="presentation"><tbody>', esc_html($title));

            foreach ($fields as $field) {
                $name = sprintf('%s[%s]', self::FIELD, $field->key);
                printf(
                    '<tr><th scope="row"><label for="%s">%s</label></th><td>%s</td></tr>',
                    esc_attr($name),
                    esc_html($field->label),
                    SettingField::render(
                        $name,
                        PostMetaBox::setting($field),
                        get_post_meta($post->ID, $field->key, true),
                    ),
                );
            }

            echo '</tbody></table>';
        }
    }

    /**
     * The address search, or a notice that links to the settings page without a token.
     *
     * The geocoder is a standalone `MapboxGeocoder` that the admin script mounts. It
     * fills the fields of this meta box; nothing is saved before the post is.
     */
    public function geocoder(): string
    {
        $token = PlacesSettings::accessToken($this->config);

        if ($token === '') {
            return sprintf(
                '<div class="notice notice-info inline"><p>%s <a href="%s">%s</a></p></div>',
                esc_html('The address search needs a Mapbox access token.'),
                esc_url(admin_url('edit.php?post_type=place&page=' . PlacesSettings::SLUG)),
                esc_html('Set the token'),
            );
        }

        $options = wp_json_encode([...['language' => $this->language()], ...$this->config->geocoderOptions]);
        $script = dirname(__DIR__, 2) . '/dist/admin.js';
        $style = dirname(__DIR__, 2) . '/dist/admin.css';

        return sprintf(
            '<h3>%s</h3><div data-component="MapboxGeocoder" data-foehn-places-geocoder data-option-access-token="%s" data-option-options="%s"></div>'
            . '<p class="description">%s</p>%s%s',
            esc_html('Address search'),
            esc_attr($token),
            esc_attr((string) $options),
            esc_html('A result fills the location and the address. Every field stays editable.'),
            is_file($style) ? '<style>' . (string) file_get_contents($style) . '</style>' : '',
            is_file($script) ? '<script type="module">' . (string) file_get_contents($script) . '</script>' : '',
        );
    }

    /**
     * Save the posted fields of a place.
     *
     * Needs the nonce and the right to edit the post, and skips autosaves and revisions.
     * A key absent from the request keeps its value, so a save without this meta box
     * clears nothing. An empty value deletes the key. Coordinates are saved as a pair:
     * a value out of range or a missing half keeps the stored coordinates, and the error
     * shows in the meta box.
     *
     * @param array<array-key, mixed> $request The posted form, as `$_POST`: slashed
     */
    public function save(int $postId, array $request): void
    {
        $nonce = $request[self::NONCE] ?? null;

        if (!is_string($nonce) || wp_verify_nonce(wp_unslash($nonce), self::ID) === false) {
            return;
        }

        if (wp_is_post_autosave($postId) !== false || wp_is_post_revision($postId) !== false) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $posted = $request[self::FIELD] ?? null;

        if (!is_array($posted)) {
            return;
        }

        $posted = wp_unslash($posted);
        $this->saveCoordinates($postId, $posted);

        foreach ($this->groups() as $title => $fields) {
            if ($title === 'Location') {
                continue;
            }

            foreach ($fields as $field) {
                $value = $posted[$field->key] ?? null;

                if (!is_scalar($value)) {
                    continue;
                }

                $value = call_user_func(PostMetaBox::setting($field)->sanitizer(), (string) $value);

                if ($value === '') {
                    delete_post_meta($postId, $field->key);

                    continue;
                }

                update_post_meta($postId, $field->key, wp_slash($value));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $posted
     */
    private function saveCoordinates(int $postId, array $posted): void
    {
        if (!array_key_exists('latitude', $posted) && !array_key_exists('longitude', $posted)) {
            return;
        }

        $latitude = is_scalar($posted['latitude'] ?? null) ? trim((string) $posted['latitude']) : '';
        $longitude = is_scalar($posted['longitude'] ?? null) ? trim((string) $posted['longitude']) : '';

        if ($latitude === '' && $longitude === '') {
            delete_post_meta($postId, 'latitude');
            delete_post_meta($postId, 'longitude');

            return;
        }

        $coordinates = PlaceCoordinates::from($latitude, $longitude);

        if ($coordinates === null) {
            set_transient(
                self::ERROR . $postId . '_' . get_current_user_id(),
                sprintf(
                    'The coordinates were not saved: the latitude must be a number from -90 to 90 and the longitude a number from -180 to 180 (got "%s", "%s").',
                    $latitude,
                    $longitude,
                ),
                60,
            );

            return;
        }

        update_post_meta($postId, 'latitude', $coordinates->latitude);
        update_post_meta($postId, 'longitude', $coordinates->longitude);
    }

    /**
     * The geocoder language: the content language of a multilingual site, else the
     * language of the admin.
     */
    private function language(): string
    {
        $multilingual = multilingual();
        $language = $multilingual?->adminLanguage() ?? $multilingual?->currentLanguage();

        return $language ?? strtolower(substr(get_user_locale(), 0, 2));
    }
}
