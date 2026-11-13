<?php

declare(strict_types=1);

namespace Demo\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * Press quotes on two projects, in the ACF fields of Demo\Fields\ProjectPressFields.
 *
 * Written with update_field(), which stores each value with the field key ACF needs
 * to read a group back. The free ACF plugin must be active: the demo requires it.
 */
#[AsSeeder(order: 25, description: 'Press quotes on two projects, in ACF fields')]
final class PressSeeder implements SeederInterface
{
    private const array PRESS = [
        'corridors' => [
            'quote' => 'Corridors finds a whole architecture in the parts of a building nobody designs to be seen.',
            'source' => 'Contrejour Review, spring 2025',
            'url' => 'https://example.com/contrejour/corridors',
            'title' => 'Read the review',
        ],
        'winter' => [
            'quote' => 'Three winters, one valley, and almost no grey.',
            'source' => 'Water Journal, issue 12',
            'url' => 'https://example.com/water-journal/winter',
            'title' => 'Read the article',
        ],
    ];

    public function run(): void
    {
        if (!function_exists('update_field')) {
            throw new RuntimeException('ACF is not active. Run `wp plugin activate advanced-custom-fields` first.');
        }

        foreach (self::PRESS as $slug => $press) {
            $project = get_page_by_path($slug, OBJECT, 'project');

            if ($project === null) {
                throw new RuntimeException("the {$slug} project does not exist. Run ProjectSeeder first.");
            }

            update_field('press_quote', $press['quote'], $project->ID);
            update_field('press_source', $press['source'], $project->ID);
            update_field(
                'press_link',
                [
                    'link' => ['url' => $press['url'], 'title' => $press['title'], 'target' => '_blank'],
                    'style' => 'link',
                ],
                $project->ID,
            );
        }
    }
}
