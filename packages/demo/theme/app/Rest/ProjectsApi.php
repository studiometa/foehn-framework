<?php

declare(strict_types=1);

namespace Demo\Rest;

use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The projects as JSON, under /wp-json/demo/v1/.
 *
 * #[AsRestRoute] goes on each method, one route each. `permission: 'public'` opens a
 * route to everyone. Any other string names a method of this class that decides,
 * here `canSeeDrafts()`. With no permission at all, a route asks for `edit_posts`.
 */
final readonly class ProjectsApi
{
    /** Every published project, in the curated order of the index. */
    #[AsRestRoute('demo/v1', '/projects', permission: 'public')]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $projects = Project::query()->orderBy('menu_order', 'ASC')->limit(100)->get();

        return new WP_REST_Response(array_map(self::summary(...), $projects));
    }

    /** Projects still in draft, for the people who write them. */
    #[AsRestRoute('demo/v1', '/projects/drafts', permission: 'canSeeDrafts')]
    public function drafts(WP_REST_Request $request): WP_REST_Response
    {
        $projects = Project::query()->status('draft')->limit(100)->get();

        return new WP_REST_Response(array_map(self::summary(...), $projects));
    }

    public function canSeeDrafts(WP_REST_Request $request): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * @return array{id: int, title: string, year: ?int, series: list<string>, link: string}
     */
    private static function summary(Project $project): array
    {
        return [
            'id' => $project->ID,
            'title' => (string) $project->title(),
            'year' => $project->year(),
            'series' => array_map(static fn($term): string => (string) $term->name, $project->categories()),
            'link' => (string) $project->link(),
        ];
    }
}
