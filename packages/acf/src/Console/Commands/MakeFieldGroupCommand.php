<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\FieldGroupStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:field-group', description: 'Create a new ACF field group class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The field group name (e.g., 'ProductFields', 'HeroFields')

    [--post-type=<post-type>]
    : Attach to a post type (e.g., 'product', 'page')

    [--page-template=<template>]
    : Attach to a page template (e.g., 'front-page', 'about')

    [--taxonomy=<taxonomy>]
    : Attach to a taxonomy (e.g., 'category', 'product_cat')

    [--title=<title>]
    : Field group title in admin (defaults to humanized name)

    [--force]
    : Overwrite an existing file. Also lets the command run in the production environment

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a field group for a post type
        wp foehn make:field-group ProductFields --post-type=product

        # Create a field group for a page template
        wp foehn make:field-group FrontPageFields --page-template=front-page

        # Create a field group for a taxonomy
        wp foehn make:field-group CategoryFields --taxonomy=category

        # Preview what would be created
        wp foehn make:field-group ProductFields --post-type=product --dry-run
    DOC, writes: true)]
final class MakeFieldGroupCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a field group name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $key = str($name)->snake()->toString();
        $title = $assocArgs['title'] ?? str($name)->replace(['-', '_'], ' ')->title()->toString();
        $postType = $assocArgs['post-type'] ?? null;
        $pageTemplate = $assocArgs['page-template'] ?? null;
        $taxonomy = $assocArgs['taxonomy'] ?? null;

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: FieldGroupStub::class,
                subdirectory: $this->getSubdirectory($postType, $pageTemplate, $taxonomy),
                className: $className,
                attributeArguments: [
                    'name' => $key,
                    'title' => $title,
                    'location' => $this->buildLocation($postType, $pageTemplate, $taxonomy),
                ],
                // The builder is seeded with the group key inside fields()
                bodyReplacements: [
                    'fields' => ["'dummy_field_group'" => "'{$key}'"],
                ],
            ),
            $assocArgs,
            'Field group created',
            ['Define the ACF fields in fields() with FieldsBuilder.'],
        );
    }

    /**
     * Get subdirectory based on location type.
     */
    private function getSubdirectory(?string $postType, ?string $pageTemplate, ?string $taxonomy): string
    {
        if ($pageTemplate !== null) {
            return 'Fields/Page';
        }

        if ($taxonomy !== null) {
            return 'Fields/Taxonomy';
        }

        if ($postType !== null) {
            return 'Fields/PostType';
        }

        return 'Fields';
    }

    /**
     * Build location rules code.
     */
    /**
     * Build the location rule the #[AsAcfFieldGroup] attribute expects.
     *
     * The attribute takes the simplified `param => value` map that
     * AcfFieldGroupDiscovery expands into full ACF location groups.
     *
     * @return array<string, string>
     */
    private function buildLocation(?string $postType, ?string $pageTemplate, ?string $taxonomy): array
    {
        if ($pageTemplate !== null) {
            return ['page_template' => $pageTemplate . '.php'];
        }

        if ($taxonomy !== null) {
            return ['taxonomy' => $taxonomy];
        }

        return ['post_type' => $postType ?? 'post'];
    }
}
