<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Seo;

use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Timber\Timber;
use WP_Post;

/**
 * The JSON-LD node of a single place, of the type `PlacesConfig::$schemaType`.
 */
#[AsJsonLd(postType: 'place')]
final readonly class PlaceJsonLd implements JsonLdProviderInterface
{
    public function __construct(
        private PlacesConfig $config,
    ) {}

    public function nodes(WP_Post $post): array
    {
        $place = Timber::get_post($post);

        return $place instanceof Place ? [$this->node($place, $post)] : [];
    }

    /**
     * The node of a place.
     *
     * @return array<string, mixed>
     */
    public function node(Place $place, WP_Post $post): array
    {
        $url = get_permalink($post);
        $node = [
            '@type' => $this->config->schemaType,
            '@id' => $url . '#place',
            'name' => get_post_field('post_title', $post),
            'url' => $url,
            'description' => wp_strip_all_tags(get_the_excerpt($post)),
            'image' => get_the_post_thumbnail_url($post, 'full') ?: null,
            'address' => array_filter([
                'streetAddress' => $place->street(),
                'postalCode' => $place->postalCode(),
                'addressLocality' => $place->city(),
                'addressCountry' => $place->country(),
            ]),
            'telephone' => $place->phone(),
            'email' => $place->email(),
            'sameAs' => $place->website(),
        ];

        if ($node['address'] !== []) {
            $node['address'] = ['@type' => 'PostalAddress', ...$node['address']];
        }

        $coordinates = $place->coordinates();

        if ($coordinates !== null) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $coordinates->latitude,
                'longitude' => $coordinates->longitude,
            ];
        }

        return array_filter($node, static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
