<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Helpers\WP;
use Studiometa\Foehn\Search\Query\SearchGroup;
use Studiometa\Foehn\Search\Query\SearchGroups;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Timber;

/**
 * The search page. studiometa/foehn-search answers the text match, with typo
 * tolerance, and WordPress pages and filters the result as on any archive.
 *
 * Without a post type in the URL, the page shows the results grouped by post type,
 * each group with its three best results and a "see all" link. With `?post_type=`, it
 * shows the paginated list of that type. The groups also give the counts of the post
 * type facet.
 */
#[AsTemplateController('search')]
final readonly class SearchController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
        private SearchGroups $groups,
    ) {}

    public function handle(TemplateContext $context): string
    {
        $context = $context
            ->with('search_query', get_search_query())
            ->with('found_posts', WP::query()->found_posts)
            ->with('groups', array_map(self::group(...), $this->groups->for(perGroup: 3)));

        if ($context->posts) {
            $context = $context->with('pagination', $context->posts->pagination());
        }

        return $this->view->render('pages/search', $context);
    }

    /**
     * A group with Timber posts, which the template prints as any other post.
     *
     * @return array{post_type: string, label: string, count: int, url: string, posts: list<mixed>}
     */
    private static function group(SearchGroup $group): array
    {
        return [
            'post_type' => $group->postType,
            'label' => $group->label,
            'count' => $group->count,
            'url' => $group->url,
            'posts' => array_map(Timber::get_post(...), $group->posts),
        ];
    }
}
