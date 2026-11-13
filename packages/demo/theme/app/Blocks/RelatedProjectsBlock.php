<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * Related projects — a block with a `posts` control.
 *
 * The control stores an ordered list of post IDs, searched among projects only. The
 * block renders them in that order, through the model's query builder, and skips a
 * project that is no longer published.
 */
#[AsBlock(
    name: 'theme/related-projects',
    title: 'Related projects',
    category: 'widgets',
    icon: 'portfolio',
    description: 'A short list of projects, in the order the author picks them.',
    keywords: ['projects', 'related', 'links'],
)]
final readonly class RelatedProjectsBlock implements BlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'projectIds' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'default' => [],
                'control' => 'posts',
                'postTypes' => ['project'],
                'label' => 'Projects',
            ],
            'title' => [
                'type' => 'string',
                'default' => 'Related projects',
                'label' => 'Title',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $ids = array_values(array_filter(
            array_map('intval', is_array($attributes['projectIds'] ?? null) ? $attributes['projectIds'] : []),
            static fn(int $id): bool => $id > 0,
        ));

        return [
            'title' => (string) ($attributes['title'] ?? ''),
            // `post__in` keeps the order the author gave; no IDs is no query at all,
            // since an empty `include()` would list every project.
            'projects' => $ids === []
                ? []
                : Project::query()->include(...$ids)->orderBy('post__in', 'ASC')->limit(count($ids))->get(),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/related-projects', $this->compose($attributes, $content, $block));
    }
}
