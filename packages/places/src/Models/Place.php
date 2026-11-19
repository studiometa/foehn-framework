<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\ValueObjects\PlaceCoordinates;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Term;

use function Tempest\Container\get;

/**
 * A located record: a store, a dealer, an agency, a partner university.
 *
 * Not final: a project extends it and maps its subclass to `place` with its own
 * `#[AsTimberModel('place')]`, which wins over this class.
 *
 * The contact keys (`phone`, `email`, `website`) are registered by PlacesHooks, and
 * only when `PlacesConfig::$contactFields` is on: an attribute cannot depend on the
 * configuration.
 */
#[AsPostType(
    name: 'place',
    singular: 'Place',
    plural: 'Places',
    hasArchive: true,
    menuIcon: 'dashicons-location',
    supports: ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
    taxonomies: ['place_type'],
)]
#[AsPostMeta(key: 'latitude', type: 'number', description: 'Latitude, from -90 to 90')]
#[AsPostMeta(key: 'longitude', type: 'number', description: 'Longitude, from -180 to 180')]
#[AsPostMeta(key: 'street', sanitize: 'sanitizeText')]
#[AsPostMeta(key: 'postal_code', sanitize: 'sanitizeText')]
#[AsPostMeta(key: 'city', sanitize: 'sanitizeText')]
#[AsPostMeta(key: 'country', sanitize: 'sanitizeText')]
class Place extends Post implements ConfiguresPostType
{
    /** The post type. A convention, not configuration. */
    public const string POST_TYPE = 'place';

    /** The address keys, in display order. */
    public const array ADDRESS_FIELDS = ['street', 'postal_code', 'city', 'country'];

    /** The contact keys, registered when `PlacesConfig::$contactFields` is on. */
    public const array CONTACT_FIELDS = ['phone', 'email', 'website'];

    /**
     * Labels, archive slug and single rewrite come from the configuration.
     */
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        $config = get(PlacesConfig::class);

        return $builder
            ->setLabels($config->singular, $config->plural)
            ->setRewriteSlug($config->archiveSlug)
            ->mergeExtraArgs(['has_archive' => $config->archiveSlug]);
    }

    /**
     * The sanitizer of the address keys, for every write: REST, import or meta box.
     */
    public static function sanitizeText(mixed $value): string
    {
        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    /**
     * The coordinates, or null when they are missing, incomplete or out of range.
     */
    public function coordinates(): ?PlaceCoordinates
    {
        return PlaceCoordinates::from($this->field('latitude'), $this->field('longitude'));
    }

    public function isLocatable(): bool
    {
        return $this->coordinates() !== null;
    }

    /**
     * The address lines: the street, the postal code with the city, the country.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        $locality = trim($this->text('postal_code') . ' ' . $this->text('city'));

        return array_values(array_filter(
            [$this->text('street'), $locality, $this->text('country')],
            static fn(string $line): bool => $line !== '',
        ));
    }

    public function street(): ?string
    {
        return $this->nullable($this->text('street'));
    }

    public function postalCode(): ?string
    {
        return $this->nullable($this->text('postal_code'));
    }

    public function city(): ?string
    {
        return $this->nullable($this->text('city'));
    }

    public function country(): ?string
    {
        return $this->nullable($this->text('country'));
    }

    public function phone(): ?string
    {
        return $this->contact('phone');
    }

    /**
     * The `tel:` URI of the phone number, or null.
     */
    public function phoneUri(): ?string
    {
        $phone = $this->phone();

        if ($phone === null) {
            return null;
        }

        $digits = (string) preg_replace('/[^0-9+]/', '', $phone);

        return $digits === '' ? null : 'tel:' . $digits;
    }

    public function email(): ?string
    {
        return $this->contact('email');
    }

    public function website(): ?string
    {
        return $this->contact('website');
    }

    /**
     * The `place_type` terms of the place.
     *
     * @return list<Term>
     */
    public function types(): array
    {
        return array_values(array_filter(
            $this->terms(['taxonomy' => PlaceType::TAXONOMY]),
            static fn(mixed $term): bool => $term instanceof Term,
        ));
    }

    /**
     * Every published place with valid coordinates.
     *
     * @return list<static>
     */
    public static function locatable(): array
    {
        return array_values(array_filter(
            static::query()->limit(-1)->get(),
            static fn(mixed $place): bool => $place instanceof Place && $place->isLocatable(),
        ));
    }

    /**
     * A stored value. Protected, so a test or a project can read values from elsewhere.
     */
    protected function field(string $key): mixed
    {
        return $this->meta($key);
    }

    private function contact(string $key): ?string
    {
        if (!get(PlacesConfig::class)->contactFields) {
            return null;
        }

        return $this->nullable($this->text($key));
    }

    private function text(string $key): string
    {
        $value = $this->field($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
