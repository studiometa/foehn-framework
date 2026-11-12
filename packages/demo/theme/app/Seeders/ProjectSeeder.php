<?php

declare(strict_types=1);

namespace Demo\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

/**
 * The six series, each with its gallery, cover and category.
 *
 * Runs after MediaSeeder: a gallery is the attachments of its series.
 */
#[AsSeeder(order: 20, description: 'The six projects, with their galleries')]
final class ProjectSeeder implements SeederInterface
{
    private const array PROJECTS = [
        'corridors' => [
            'title' => 'Corridors',
            'excerpt' => 'Six months spent in the circulation spaces of office buildings, photographing what nobody looks at on the way through.',
            'content' => "<p>The series began with a delay: two hours waiting in a lobby, and nothing to look at but a corridor. Office buildings treat their circulation as plumbing — no intention, no display. That is exactly what makes them photogenic. The light is blunt, the lines are clean, and nothing has been arranged to be seen.</p>\n<p>Shot on film, square negatives cropped to 4:3 at the print stage.</p>",
            'client' => 'Self-initiated',
            'year' => 2024,
            'location' => 'Strasbourg, Basel',
            'camera' => 'Hasselblad 500C/M — Ilford HP5+',
            'category' => 'Architecture',
        ],
        'silhouettes' => [
            'title' => 'Silhouettes',
            'excerpt' => 'Bodies against the light, reduced to an outline. What is left of a portrait once you take the face away.',
            'content' => "<p>Is a portrait without a face still a portrait? The series started as a constraint on a commission — a client who wanted nobody identifiable — and carried on long after the work was delivered.</p>\n<p>Every frame is a single exposure. No outline has been retouched.</p>",
            'client' => 'Contrejour Review',
            'year' => 2023,
            'location' => 'Studio, Strasbourg',
            'camera' => 'Nikon FM2 — Kodak Tri-X 400',
            'category' => 'Portrait',
        ],
        'winter' => [
            'title' => 'Winter',
            'excerpt' => 'The Vosges under snow, where the landscape loses its mid-tones and keeps only two values.',
            'content' => "<p>Snow does to a landscape what black and white does to a photograph: it removes everything that is not structure. A single tree in a snowed field stops being a tree and becomes a stroke.</p>\n<p>Made over three winters, always in the same place.</p>",
            'client' => 'Self-initiated',
            'year' => 2022,
            'location' => 'Vosges',
            'camera' => 'Mamiya 7 — Fuji Acros 100',
            'category' => 'Landscape',
        ],
        'objects' => [
            'title' => 'Objects',
            'excerpt' => 'Contemporary still life: whatever is lying on the table, lit as though it were a subject.',
            'content' => '<p>A publishing commission that asked for “neutral” images to sit alongside a text. The brief said: nothing that tells a story. It turned out to be impossible to honour.</p>',
            'client' => 'Grille Editions',
            'year' => 2024,
            'location' => 'Studio, Strasbourg',
            'camera' => 'Digital — 100 mm macro',
            'category' => 'Still life',
        ],
        'osaka' => [
            'title' => 'Osaka',
            'excerpt' => 'Three weeks walking a city whose signs I could not read.',
            'content' => "<p>Not understanding a city's language is the best way to see its shape. With the text gone you are left looking at surfaces, at floors, at the way light falls between two buildings.</p>",
            'client' => 'Self-initiated',
            'year' => 2023,
            'location' => 'Osaka, Japan',
            'camera' => 'Leica M6 — Kodak Tri-X 400',
            'category' => 'Documentary',
        ],
        'editions' => [
            'title' => 'Editions',
            'excerpt' => 'Commissioned work for independent publishers: books, journals, stationery.',
            'content' => '<p>Photographing printed paper means solving one simple, tedious problem: the white of the paper is never the white of the backdrop. The series documents a method as much as a set of objects.</p>',
            'client' => 'Water Journal, Grille Editions',
            'year' => 2025,
            'location' => 'Studio, Strasbourg',
            'camera' => 'Digital — copy stand',
            'category' => 'Still life',
        ],
    ];

    /**
     * A paragraph bound to the `camera` meta through core's `core/post-meta` source.
     *
     * The value is stored once, as meta, and the page prints it twice: in the facts
     * beside the title, and here in the content, with no binding source of our own.
     */
    private const string CAMERA_BINDING = "\n<!-- wp:paragraph {\"className\":\"label\"} -->\n<p class=\"label\">Shot on</p>\n<!-- /wp:paragraph -->\n<!-- wp:paragraph {\"metadata\":{\"bindings\":{\"content\":{\"source\":\"core/post-meta\",\"args\":{\"key\":\"camera\"}}}}} -->\n<p></p>\n<!-- /wp:paragraph -->";

    public function run(): void
    {
        $order = 0;

        foreach (self::PROJECTS as $slug => $project) {
            $gallery = Photographs::ofProject($slug);

            $id = Upsert::post(
                'project',
                $slug,
                [
                    'post_title' => $project['title'],
                    'post_excerpt' => $project['excerpt'],
                    'post_content' => $project['content'] . self::CAMERA_BINDING,
                    'menu_order' => ++$order,
                ],
                [
                    'client' => $project['client'],
                    'year' => $project['year'],
                    'location' => $project['location'],
                    'camera' => $project['camera'],
                    'gallery' => $gallery,
                ],
            );

            if ($gallery !== []) {
                set_post_thumbnail($id, $gallery[0]);
            }

            wp_set_object_terms($id, $project['category'], 'project_category');
        }
    }
}
