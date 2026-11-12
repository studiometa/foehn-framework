<?php

declare(strict_types=1);

namespace Demo\Console;

use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

/**
 * `wp app projects:stats`: the series of the portfolio, with their photographs.
 *
 * A command of the project, so it goes under `wp app`, the default `cliNamespace`, and
 * not under `wp foehn`. It only reads, so it does not declare `writes` and runs in
 * production like any other command.
 */
#[AsCliCommand(
    name: 'projects:stats',
    description: 'List the series of the portfolio with their year and photographs',
    longDescription: <<<'DOC'
        ## EXAMPLES

            wp app projects:stats
        DOC,
)]
final readonly class ProjectsStatsCommand implements CliCommandInterface
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
        $rows = array_map(static fn(Project $project): array => [
            'series' => $project->title(),
            'year' => (string) ($project->year() ?? ''),
            'photographs' => $project->photographCount(),
            'order' => $project->menu_order,
        ], Project::query()->orderBy('menu_order', 'ASC')->get());

        $this->cli->table($rows, ['series', 'year', 'photographs', 'order']);
        $this->cli->log(sprintf(
            '%d series, %d photographs.',
            count($rows),
            array_sum(array_column($rows, 'photographs')),
        ));
    }
}
