<?php

declare(strict_types=1);

namespace Demo\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

/**
 * The venues of the place locator, with their types, regions and opening hours.
 *
 * One venue has no coordinates: it is listed nowhere, because the search only reads
 * places it can locate, and it is never put at [0, 0].
 */
#[AsSeeder(order: 45, description: 'Nine venues for the place locator')]
final class PlaceSeeder implements SeederInterface
{
    private const array TYPES = ['gallery' => 'Gallery', 'bookshop' => 'Bookshop', 'museum' => 'Museum'];

    private const array REGIONS = [
        'ile-de-france' => 'Île-de-France',
        'auvergne-rhone-alpes' => 'Auvergne-Rhône-Alpes',
        'provence' => 'Provence',
        'abroad' => 'Abroad',
    ];

    private const array VENUES = [
        'galerie-du-jour' => [
            'Galerie du Jour',
            48.8606,
            2.3376,
            '44 rue Quincampoix',
            '75004',
            'Paris',
            'France',
            'gallery',
            'ile-de-france',
            'Tue–Sat, 11am–7pm',
        ],
        'librairie-des-quais' => [
            'Librairie des Quais',
            48.8530,
            2.3470,
            '12 quai Saint-Michel',
            '75005',
            'Paris',
            'France',
            'bookshop',
            'ile-de-france',
            'Mon–Sat, 10am–8pm',
        ],
        'maison-de-l-image' => [
            'Maison de l\'image',
            48.8049,
            2.1204,
            '3 rue de la Paroisse',
            '78000',
            'Versailles',
            'France',
            'museum',
            'ile-de-france',
            'Wed–Sun, 10am–6pm',
        ],
        'halle-aux-photographies' => [
            'Halle aux photographies',
            45.7640,
            4.8357,
            '20 place des Terreaux',
            '69001',
            'Lyon',
            'France',
            'gallery',
            'auvergne-rhone-alpes',
            'Wed–Sun, 2pm–7pm',
        ],
        'librairie-croix-rousse' => [
            'Librairie de la Croix-Rousse',
            45.7746,
            4.8320,
            '8 boulevard de la Croix-Rousse',
            '69004',
            'Lyon',
            'France',
            'bookshop',
            'auvergne-rhone-alpes',
            'Tue–Sat, 10am–7pm',
        ],
        'musee-de-grenoble-photo' => [
            'Cabinet photographique',
            45.1940,
            5.7323,
            '5 place de Lavalette',
            '38000',
            'Grenoble',
            'France',
            'museum',
            'auvergne-rhone-alpes',
            'Wed–Mon, 10am–6:30pm',
        ],
        'atelier-arles' => [
            'Atelier d\'Arles',
            43.6766,
            4.6278,
            '16 rue du Docteur Fanton',
            '13200',
            'Arles',
            'France',
            'gallery',
            'provence',
            'Every day in summer, 10am–7pm',
        ],
        'fotobuch-berlin' => [
            'Fotobuch Berlin',
            52.5296,
            13.4019,
            'Torstraße 109',
            '10119',
            'Berlin',
            'Germany',
            'bookshop',
            'abroad',
            'Mon–Sat, 11am–7pm',
        ],
        'online-shop' => ['Online print shop', null, null, '', '', '', '', 'bookshop', 'abroad', ''],
    ];

    public function run(): void
    {
        $terms = [
            'place_type' => self::terms('place_type', self::TYPES),
            'venue_region' => self::terms('venue_region', self::REGIONS),
        ];

        foreach (self::VENUES as $slug => [
            $title,
            $latitude,
            $longitude,
            $street,
            $postalCode,
            $city,
            $country,
            $type,
            $region,
            $hours,
        ]) {
            $id = Upsert::post(
                'place',
                $slug,
                [
                    'post_title' => $title,
                    'post_content' => sprintf('Prints from the series are shown at %s.', $title),
                ],
                [
                    'latitude' => $latitude ?? '',
                    'longitude' => $longitude ?? '',
                    'street' => $street,
                    'postal_code' => $postalCode,
                    'city' => $city,
                    'country' => $country,
                    'opening_hours' => $hours,
                ],
            );

            wp_set_object_terms($id, [$terms['place_type'][$type]], 'place_type');
            wp_set_object_terms($id, [$terms['venue_region'][$region]], 'venue_region');
        }
    }

    /**
     * The term ids of a taxonomy, by slug, created when missing.
     *
     * @param array<string, string> $names
     * @return array<string, int>
     */
    private static function terms(string $taxonomy, array $names): array
    {
        $ids = [];

        foreach ($names as $slug => $name) {
            $term = get_term_by('slug', $slug, $taxonomy);

            if ($term instanceof \WP_Term) {
                $ids[$slug] = $term->term_id;

                continue;
            }

            $created = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
            $ids[$slug] = is_array($created) ? (int) $created['term_id'] : 0;
        }

        return $ids;
    }
}
