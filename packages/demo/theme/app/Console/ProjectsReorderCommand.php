<?php

declare(strict_types=1);

namespace Demo\Console;

use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

/**
 * `wp app projects:reorder`: put the newest series first on the homepage.
 *
 * It changes content, so it declares `writes: true`. In production, Føhn stops it
 * before it runs unless the caller adds `--dry-run`, which writes nothing, or
 * `--force`. The long description declares both: WP-CLI refuses an option it does not
 * declare.
 */
#[AsCliCommand(
    name: 'projects:reorder',
    description: 'Order the series by year, the newest first',
    writes: true,
    longDescription: <<<'DOC'
        ## OPTIONS

        [--dry-run]
        : Show the new order and write nothing.

        [--force]
        : Run in production.

        ## EXAMPLES

            wp app projects:reorder --dry-run
            wp app projects:reorder
        DOC,
)]
final readonly class ProjectsReorderCommand implements CliCommandInterface
{
    public function __construct(
        private WpCli $cli,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $projects = Project::all();

        // The newest year first, then the title, so two runs give the same order.
        usort(
            $projects,
            static fn(Project $a, Project $b): int => (
                [$b->year() ?? 0, $a->title()] <=> [
                    $a->year() ?? 0,
                    $b->title(),
                ]
            ),
        );

        $rows = [];
        $changed = 0;

        foreach ($projects as $index => $project) {
            $order = $index + 1;
            $rows[] = [
                'series' => $project->title(),
                'year' => $project->year(),
                'from' => $project->menu_order,
                'to' => $order,
            ];

            if ($project->menu_order === $order) {
                continue;
            }

            $changed++;

            if (!$dryRun) {
                wp_update_post(['ID' => $project->ID, 'menu_order' => $order]);
            }
        }

        $this->cli->table($rows, ['series', 'year', 'from', 'to']);

        if ($dryRun) {
            $this->cli->log(sprintf('Dry run: %d series would move. Nothing was written.', $changed));

            return;
        }

        $this->cli->success(sprintf('%d series moved.', $changed));
    }
}
