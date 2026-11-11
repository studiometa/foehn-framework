<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\HooksStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:hooks', description: 'Create a new hooks class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The hooks class name (e.g., 'seo', 'admin', 'security')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Hooks)

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a SEO hooks class
        wp foehn make:hooks seo

        # Create admin hooks
        wp foehn make:hooks admin --class=AdminHooks

        # Create security-related hooks
        wp foehn make:hooks security

        # Preview what would be created
        wp foehn make:hooks seo --dry-run
    DOC)]
final class MakeHooksCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a hooks class name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Hooks';

        $this->scaffolder->scaffold(
            new GenerationRequest(stub: HooksStub::class, subdirectory: 'Hooks', className: $className, replacements: [
                'DummyHooks' => $className,
            ]),
            $assocArgs,
            'Hooks class created',
            [
                "Add hooks with #[AsAction('hook_name')] and #[AsFilter('hook_name')] methods.",
            ],
        );
    }
}
