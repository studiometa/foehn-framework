<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Skills;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;

#[AsCliCommand(
    name: 'agents-md',
    description: 'Write or refresh the Føhn block in the project AGENTS.md',
    longDescription: <<<'DOC'
        ## DESCRIPTION

        Writes the Føhn block in the AGENTS.md file at the project root: a short list
        of what a coding agent does first in a Føhn project, and an index of the Agent
        Skills of the installed packages, one line for each skill and reference file,
        with the command that prints it.

        The block is between <!-- foehn:begin --> and <!-- foehn:end -->. The command
        replaces what is between the markers and changes nothing else in the file. A
        file without the markers gets the block at its end. A missing file is created
        with the block only. When the block is already current, nothing is written.

        Run it again after composer update or npm update, so the index follows the
        installed skills, and commit the change.

        ## OPTIONS

        [--dry-run]
        : Write nothing. Print the block and say what the command would do.

        [--force]
        : Run in the production environment.

        ## EXAMPLES

            # Write or refresh the block
            wp foehn agents-md

            # See the block first
            wp foehn agents-md --dry-run
        DOC,
    writes: true,
)]
final readonly class AgentsMdCommand implements CliCommandInterface
{
    public const string FILE = 'AGENTS.md';

    public function __construct(
        private WpCli $cli,
        private DiscoveryLocations $locations,
        private AgentsBlock $block,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $root = $this->locations->resolveRootPath();

        if ($root === null) {
            $this->cli->error(
                'Could not find the project root: no directory above the theme holds composer.json and vendor/composer/installed.json.',
            );

            return;
        }

        $path = $root . '/' . self::FILE;
        $contents = is_file($path) ? file_get_contents($path) : null;

        if ($contents === false) {
            $this->cli->error(sprintf('Cannot read %s: the user that runs WP-CLI needs read access to it.', $path));

            return;
        }

        $block = $this->block->render();

        try {
            $updated = AgentsBlock::merge($contents, $block);
        } catch (\InvalidArgumentException $exception) {
            $this->cli->error(sprintf(
                '%s has broken Føhn markers, so it was not changed. %s Remove the extra markers, or both, and run the command again.',
                $path,
                $exception->getMessage(),
            ));

            return;
        }

        $done = match (true) {
            $updated === $contents => sprintf('The Føhn block in %s is up to date.', self::FILE),
            $contents === null => sprintf('Created %s with the Føhn block.', self::FILE),
            AgentsBlock::isIn($contents) => sprintf('Updated the Føhn block in %s.', self::FILE),
            default => sprintf('Added the Føhn block at the end of %s.', self::FILE),
        };

        if (($assocArgs['dry-run'] ?? null) !== null) {
            $this->cli->line($block);
            $this->cli->line('');
            $this->cli->log(sprintf('Dry run, nothing was written. Without --dry-run: %s', $done));

            return;
        }

        if ($updated === $contents) {
            $this->cli->success($done);

            return;
        }

        if (file_put_contents($path, $updated) === false) {
            $this->cli->error(sprintf('Could not write %s.', $path));

            return;
        }

        $this->cli->success($done);
    }
}
