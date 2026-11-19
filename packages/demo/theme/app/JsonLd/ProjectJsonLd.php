<?php

declare(strict_types=1);

namespace Demo\JsonLd;

use Demo\Models\Photograph;
use Demo\Models\Project;
use Spatie\SchemaOrg\Schema;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use Timber\Timber;
use WP_Post;

/**
 * The schema.org node of a project: a `CreativeWorkSeries` that holds its photographs.
 *
 * The demo has no SEO plugin, so Føhn prints the node in a
 * `<script type="application/ld+json">` in `<head>`. The values come from the
 * accessors of Demo\Models\Project, so the page and its JSON-LD say the same thing.
 */
#[AsJsonLd(postType: 'project')]
final class ProjectJsonLd implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        $project = Timber::get_post($post);

        if (!$project instanceof Project) {
            return [];
        }

        $url = (string) $project->link();

        $series = Schema::creativeWorkSeries()
            ->identifier($url . '#series')
            ->name($project->title())
            ->url($url)
            ->mainEntityOfPage($url)
            ->description($project->post_excerpt)
            ->hasPart(array_map(static fn(Photograph $photograph) => Schema::photograph()
                ->contentUrl($photograph->src())
                ->creditText($photograph->credit()?->title), $project->photographs()));

        if ($project->location() !== null) {
            $series->locationCreated(Schema::place()->name($project->location()));
        }

        if ($project->client() !== null) {
            $series->sponsor(Schema::organization()->name($project->client()));
        }

        return [$series];
    }
}
