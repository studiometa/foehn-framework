<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\ContextStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:context', description: 'Create a new context provider (view composer)', longDescription: <<<'DOC'
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
    : Show what would be created without creating

    ## EXAMPLES

        # Create a global context provider
        wp tempest make:context GlobalContext --global

        # Create a context for single posts
        wp tempest make:context SingleContext --templates=single,single-*

        # Create a context for product templates
        wp tempest make:context ProductContext --templates=single-product,archive-product

        # Preview what would be created
        wp tempest make:context GlobalContext --global --dry-run
    DOC)]
final class MakeContextCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
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
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $targetPath = $this->getTargetPath('Context', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format templates array for replacement
        $templatesCode = "['" . implode("', '", $templates) . "']";

        $content = $this->generateClassFile(
            stubClass: ContextStub::class,
            targetPath: $targetPath,
            replacements: [
                "['dummy-template', 'dummy-template-*']" => $templatesCode,
                'DummyContext' => $className,
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Context provider created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log('Edit the compose() method to add data to the Timber context.');
        $this->cli->line('');
        $this->cli->log('Template patterns:');
        foreach ($templates as $template) {
            $this->cli->log("  - {$template}");
        }
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
