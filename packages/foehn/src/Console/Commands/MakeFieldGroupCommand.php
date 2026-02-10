<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
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
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a field group for a post type
        wp tempest make:field-group ProductFields --post-type=product

        # Create a field group for a page template
        wp tempest make:field-group FrontPageFields --page-template=front-page

        # Create a field group for a taxonomy
        wp tempest make:field-group CategoryFields --taxonomy=category

        # Preview what would be created
        wp tempest make:field-group ProductFields --post-type=product --dry-run
    DOC)]
final class MakeFieldGroupCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
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
        $force = isset($assocArgs['force']);
        $dryRun = isset($assocArgs['dry-run']);

        // Determine subdirectory based on location type
        $subdirectory = $this->getSubdirectory($postType, $pageTemplate, $taxonomy);
        $targetPath = $this->getTargetPath($subdirectory, $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Build location rules
        $locationCode = $this->buildLocationCode($postType, $pageTemplate, $taxonomy);

        $content = $this->generateClassFile(
            stubClass: FieldGroupStub::class,
            targetPath: $targetPath,
            replacements: [
                'dummy_field_group' => $key,
                'Dummy Field Group' => $title,
                "['post_type', '==', 'post']" => $locationCode,
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Field group created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log('Edit the fields() method to define your ACF fields using FieldsBuilder.');
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
    private function buildLocationCode(?string $postType, ?string $pageTemplate, ?string $taxonomy): string
    {
        if ($pageTemplate !== null) {
            return "['page_template', '==', '{$pageTemplate}.php']";
        }

        if ($taxonomy !== null) {
            return "['taxonomy', '==', '{$taxonomy}']";
        }

        if ($postType !== null) {
            return "['post_type', '==', '{$postType}']";
        }

        return "['post_type', '==', 'post']";
    }
}
