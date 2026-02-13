<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\AcfBlockStub;
use Studiometa\Foehn\Console\WpCli;

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

    [--fields=<fields>]
    : Comma-separated field shortcuts to generate.
      Available: text, wysiwyg, image, gallery, url, cta, select, repeater
      Example: --fields=text,wysiwyg,image,cta

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a simple ACF block
        wp tempest make:acf-block hero

        # Create with custom title
        wp tempest make:acf-block testimonial --title="Customer Testimonial"

        # Create with pre-generated fields
        wp tempest make:acf-block hero --fields=wysiwyg,image,cta

        # Create with edit mode
        wp tempest make:acf-block contact-form --mode=edit

        # Preview what would be created
        wp tempest make:acf-block hero --dry-run
    DOC)]
final class MakeAcfBlockCommand implements CliCommandInterface
{
    use GeneratesFiles;

    /**
     * Field type definitions for --fields flag.
     *
     * @var array<string, array{method: string, args: array<string, mixed>, contextKey?: string}>
     */
    private const FIELD_TYPES = [
        'text' => [
            'method' => 'addText',
            'args' => ['label' => 'Text'],
        ],
        'wysiwyg' => [
            'method' => 'addWysiwyg',
            'args' => ['label' => 'Content', 'media_upload' => false, 'tabs' => 'visual'],
        ],
        'image' => [
            'method' => 'addImage',
            'args' => ['label' => 'Image', 'return_format' => 'id', 'preview_size' => 'medium'],
        ],
        'gallery' => [
            'method' => 'addGallery',
            'args' => ['label' => 'Gallery', 'return_format' => 'id', 'preview_size' => 'medium'],
        ],
        'url' => [
            'method' => 'addUrl',
            'args' => ['label' => 'URL'],
        ],
        'select' => [
            'method' => 'addSelect',
            'args' => ['label' => 'Select', 'choices' => ['option1' => 'Option 1', 'option2' => 'Option 2']],
        ],
        'repeater' => [
            'method' => 'addRepeater',
            'args' => ['label' => 'Items', 'min' => 0, 'layout' => 'block'],
        ],
    ];

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
        $fields = ($assocArgs['fields'] ?? null) !== null ? array_map('trim', explode(',', $assocArgs['fields'])) : [];
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        // Validate mode
        if (!in_array($mode, ['preview', 'edit', 'auto'], true)) {
            $this->cli->error("Invalid mode '{$mode}'. Must be 'preview', 'edit', or 'auto'.");

            return;
        }

        // Validate fields
        $invalidFields = array_diff($fields, array_keys(self::FIELD_TYPES), ['cta']);
        if (count($invalidFields) > 0) {
            $this->cli->error(
                'Invalid field types: '
                . implode(', ', $invalidFields)
                . "\n"
                . 'Available: text, wysiwyg, image, gallery, url, cta, select, repeater',
            );

            return;
        }

        $targetPath = $this->getTargetPath('Blocks', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Generate field key from name
        $fieldKey = str($name)->snake()->toString();

        // Generate fields code and compose data
        $fieldsCode = $this->generateFieldsCode($fields);
        $composeCode = $this->generateComposeCode($fields);

        $content = $this->generateClassFile(
            stubClass: AcfBlockStub::class,
            targetPath: $targetPath,
            replacements: [
                'dummy-acf-block' => $name,
                'Dummy ACF Block' => $title,
                "category: 'common'" => "category: '{$category}'",
                "mode: 'preview'" => "mode: '{$mode}'",
                'dummy_acf_block' => $fieldKey,
                $this->getDefaultFieldsCode() => $fieldsCode,
                $this->getDefaultComposeCode() => $composeCode,
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("ACF block created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/blocks/{$name}.twig");
    }

    /**
     * Generate ACF fields code from field shortcuts.
     *
     * @param string[] $fields Field shortcuts
     */
    private function generateFieldsCode(array $fields): string
    {
        if (count($fields) === 0) {
            return $this->getDefaultFieldsCode();
        }

        $lines = [];

        foreach ($fields as $field) {
            $this->addFieldLines($field, $lines);
        }

        return "\$fields\n            " . implode("\n            ", $lines) . ';';
    }

    /**
     * Add field lines based on field type.
     *
     * @param string[] $lines Lines array to append to (passed by reference)
     */
    private function addFieldLines(string $field, array &$lines): void
    {
        if ($field === 'cta') {
            // CTA is a special case: label + url
            $lines[] = $this->formatFieldCall('addText', 'cta_label', ['label' => 'CTA Label']);
            $lines[] = $this->formatFieldCall('addUrl', 'cta_url', ['label' => 'CTA URL']);

            return;
        }

        if ($field === 'repeater') {
            // Repeater needs special handling with endRepeater
            $config = self::FIELD_TYPES[$field];
            $lines[] = $this->formatFieldCall($config['method'], $field, $config['args']);
            $lines[] = '                ->addText(\'item_title\', [\'label\' => \'Title\'])';
            $lines[] = '            ->endRepeater()';

            return;
        }

        $config = self::FIELD_TYPES[$field];
        $lines[] = $this->formatFieldCall($config['method'], $field, $config['args']);
    }

    /**
     * Format a single field method call.
     *
     * @param array<string, mixed> $args
     */
    private function formatFieldCall(string $method, string $name, array $args): string
    {
        $argsCode = $this->formatArrayCode($args);

        return "->{$method}('{$name}', {$argsCode})";
    }

    /**
     * Format an array as PHP code.
     *
     * @param array<string, mixed> $array
     */
    private function formatArrayCode(array $array): string
    {
        $pairs = [];

        foreach ($array as $key => $value) {
            $valueCode = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value) => (string) $value,
                is_array($value) => $this->formatArrayCode($value),
                default => "'" . addslashes((string) $value) . "'",
            };

            $pairs[] = "'{$key}' => {$valueCode}";
        }

        return '[' . implode(', ', $pairs) . ']';
    }

    /**
     * Generate compose method code from field shortcuts.
     *
     * @param string[] $fields Field shortcuts
     */
    private function generateComposeCode(array $fields): string
    {
        if (count($fields) === 0) {
            return $this->getDefaultComposeCode();
        }

        $lines = ["'block' => \$block,"];

        foreach ($fields as $field) {
            $this->addComposeLines($field, $lines);
        }

        return "return [\n            " . implode("\n            ", $lines) . "\n        ];";
    }

    /**
     * Add compose lines based on field type.
     *
     * @param string[] $lines Lines array to append to (passed by reference)
     */
    private function addComposeLines(string $field, array &$lines): void
    {
        if ($field === 'cta') {
            $lines[] = "'cta_label' => \$fields['cta_label'] ?? '',";
            $lines[] = "'cta_url' => \$fields['cta_url'] ?? '',";

            return;
        }

        if ($field === 'repeater') {
            $lines[] = "'repeater' => \$fields['repeater'] ?? [],";

            return;
        }

        $default = in_array($field, ['image', 'gallery'], true) ? 'null' : "''";
        $lines[] = "'{$field}' => \$fields['{$field}'] ?? {$default},";
    }

    /**
     * Get the default fields code from the stub.
     */
    private function getDefaultFieldsCode(): string
    {
        return <<<'CODE'
            $fields
                        ->addText('title', [
                            'label' => 'Title',
                            'required' => true,
                        ])
                        ->addWysiwyg('content', [
                            'label' => 'Content',
                            'media_upload' => false,
                            'tabs' => 'visual',
                        ])
                        ->addImage('image', [
                            'label' => 'Image',
                            'return_format' => 'id',
                            'preview_size' => 'medium',
                        ]);
            CODE;
    }

    /**
     * Get the default compose code from the stub.
     */
    private function getDefaultComposeCode(): string
    {
        return <<<'CODE'
            return [
                        'block' => $block,
                        'title' => $fields['title'] ?? '',
                        'content' => $fields['content'] ?? '',
                        'image' => $fields['image'] ?? null,
                    ];
            CODE;
    }
}
