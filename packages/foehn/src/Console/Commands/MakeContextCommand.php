<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\ContextProviderStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(
    name: 'make:context',
    description: 'Create a new context provider (view composer)',
    longDescription: <<<'DOC'
        ## OPTIONS

        <name>
        : The context provider name (e.g., 'GlobalContext', 'SingleContext')

        [--templates=<templates>]
        : Comma-separated template patterns to match (e.g., 'single,single-*')
          Use '*' for global context that applies to all templates.

        [--global]
        : Shorthand for --templates=* (applies to all templates)

        [--force]
        : Overwrite existing file

        [--dry-run]
        : Write nothing. Show the file that would be written, or report that it exists

        [--format=<format>]
        : Output format. text (default) or json. json prints the written files, their status and the next steps

        ## EXAMPLES

            # Create a global context provider
            wp foehn make:context GlobalContext --global

            # Create a context for single posts
            wp foehn make:context SingleContext --templates=single,single-*

            # Create a context for product templates
            wp foehn make:context ProductContext --templates=single-product,archive-product

            # Preview what would be created
            wp foehn make:context GlobalContext --global --dry-run
        DOC,
)]
final class MakeContextCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a context provider name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $isGlobal = ($assocArgs['global'] ?? null) !== null;
        $templates = $this->resolveTemplates($isGlobal, $assocArgs, $name);

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: ContextProviderStub::class,
                subdirectory: 'Context',
                className: $className,
                attributeArguments: ['templates' => $templates],
            ),
            $assocArgs,
            'Context provider created',
            [
                'Add data to the view context in provide().',
                'Template patterns: ' . implode(', ', $templates),
            ],
        );
    }

    /**
     * Resolve templates from arguments.
     *
     * @param array<string, string> $assocArgs
     * @return string[]
     */
    private function resolveTemplates(bool $isGlobal, array $assocArgs, string $name): array
    {
        if ($isGlobal) {
            return ['*'];
        }

        if (($assocArgs['templates'] ?? null) !== null) {
            return array_map('trim', explode(',', $assocArgs['templates']));
        }

        return [$this->guessTemplateFromName($name)];
    }

    /**
     * Guess template pattern from context name.
     */
    private function guessTemplateFromName(string $name): string
    {
        // Remove common suffixes
        $base = str($name)->replace(['Context', 'Composer', 'Provider'], '')->kebab()->toString();

        if ($base === '' || $base === 'global') {
            return '*';
        }

        return $base;
    }
}
