# API Reference

This section documents all attributes, interfaces, and core classes in Foehn.

## Attributes

Attributes are PHP 8 annotations that enable auto-discovery and registration of WordPress components.

### Hooks

| Attribute                          | Description                      |
| ---------------------------------- | -------------------------------- |
| [`#[AsAction]`](./as-action)       | Register a WordPress action hook |
| [`#[AsFilter]`](./as-filter)       | Register a WordPress filter hook |
| [`#[AsShortcode]`](./as-shortcode) | Register a shortcode handler     |

### Content Types

| Attribute                               | Description                                |
| --------------------------------------- | ------------------------------------------ |
| [`#[AsPostType]`](./as-post-type)       | Register a custom post type                |
| [`#[AsTaxonomy]`](./as-taxonomy)        | Register a custom taxonomy                 |
| [`#[AsTimberModel]`](./as-timber-model) | Map Timber class without type registration |

### Views

| Attribute                                             | Description                    |
| ----------------------------------------------------- | ------------------------------ |
| [`#[AsViewComposer]`](./as-view-composer)             | Add data to specific templates |
| [`#[AsTemplateController]`](./as-template-controller) | Handle template rendering      |

### Blocks

| Attribute                                   | Description                       |
| ------------------------------------------- | --------------------------------- |
| [`#[AsBlock]`](./as-block)                  | Register a native Gutenberg block |
| [`#[AsAcfBlock]`](./as-acf-block)           | Register an ACF block             |
| [`#[AsBlockPattern]`](./as-block-pattern)   | Register a block pattern          |
| [`#[AsBlockCategory]`](./as-block-category) | Register a block category         |

### API & CLI

| Attribute                             | Description                  |
| ------------------------------------- | ---------------------------- |
| [`#[AsRestRoute]`](./as-rest-route)   | Register a REST API endpoint |
| [`#[AsCliCommand]`](./as-cli-command) | Register a WP-CLI command    |

## Interfaces

Interfaces define contracts for classes used with specific attributes.

| Interface                                                        | Used with                       |
| ---------------------------------------------------------------- | ------------------------------- |
| [`BlockInterface`](./block-interface)                            | `#[AsBlock]`                    |
| [`InteractiveBlockInterface`](./interactive-block-interface)     | `#[AsBlock]` with interactivity |
| [`AcfBlockInterface`](./acf-block-interface)                     | `#[AsAcfBlock]`                 |
| [`ViewComposerInterface`](./view-composer-interface)             | `#[AsViewComposer]`             |
| [`TemplateControllerInterface`](./template-controller-interface) | `#[AsTemplateController]`       |
| [`BlockPatternInterface`](./block-pattern-interface)             | `#[AsBlockPattern]` (optional)  |

## Core

| Class                  | Description             |
| ---------------------- | ----------------------- |
| [`Kernel`](./kernel)   | Main bootstrap class    |
| [`Helpers`](./helpers) | Global helper functions |
