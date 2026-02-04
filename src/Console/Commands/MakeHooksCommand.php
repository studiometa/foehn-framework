<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\HooksStub;
use Studiometa\WPTempest\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:hooks', description: 'Create a new hooks class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The hooks class name (e.g., 'seo', 'admin', 'security')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Hooks)

    [--force]
    : Overwrite existing file

    ## EXAMPLES

        # Create a SEO hooks class
        wp tempest make:hooks seo

        # Create admin hooks
        wp tempest make:hooks admin --class=AdminHooks

        # Create security-related hooks
        wp tempest make:hooks security
    DOC)]
final class MakeHooksCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a hooks class name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Hooks';
        $force = isset($assocArgs['force']);

        $targetPath = $this->getTargetPath('Hooks', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $this->generateClassFile(stubClass: HooksStub::class, targetPath: $targetPath, replacements: [
            'DummyHooks' => $className,
        ]);

        $this->cli->success("Hooks class created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log('Add your hooks using:');
        $this->cli->log('  #[AsAction(\'hook_name\')] for actions');
        $this->cli->log('  #[AsFilter(\'hook_name\')] for filters');
    }
}
