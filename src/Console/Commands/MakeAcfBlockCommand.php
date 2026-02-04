<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\AcfBlockStub;
use Studiometa\WPTempest\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:acf-block', description: 'Create a new ACF block class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The block name (e.g., 'hero', 'testimonial')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Block)

    [--title=<title>]
    : Block title (defaults to humanized name)

    [--category=<category>]
    : Block category (defaults to 'common')

    [--mode=<mode>]
    : Display mode: 'preview', 'edit', or 'auto' (defaults to 'preview')

    [--force]
    : Overwrite existing file

    ## EXAMPLES

        # Create a simple ACF block
        wp tempest make:acf-block hero

        # Create with custom title
        wp tempest make:acf-block testimonial --title="Customer Testimonial"

        # Create with edit mode
        wp tempest make:acf-block contact-form --mode=edit
    DOC)]
final class MakeAcfBlockCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a block name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Block';
        $title = $assocArgs['title'] ?? str($name)->replace('-', ' ')->title()->toString();
        $category = $assocArgs['category'] ?? 'common';
        $mode = $assocArgs['mode'] ?? 'preview';
        $force = isset($assocArgs['force']);

        // Validate mode
        if (!in_array($mode, ['preview', 'edit', 'auto'], true)) {
            $this->cli->error("Invalid mode '{$mode}'. Must be 'preview', 'edit', or 'auto'.");

            return;
        }

        $targetPath = $this->getTargetPath('Blocks', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Generate field key from name
        $fieldKey = str($name)->snake()->toString();

        $this->generateClassFile(stubClass: AcfBlockStub::class, targetPath: $targetPath, replacements: [
            'dummy-acf-block' => $name,
            'Dummy ACF Block' => $title,
            "category: 'common'" => "category: '{$category}'",
            "mode: 'preview'" => "mode: '{$mode}'",
            'dummy_acf_block' => $fieldKey,
        ]);

        $this->cli->success("ACF block created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/blocks/{$name}.twig");
    }
}
