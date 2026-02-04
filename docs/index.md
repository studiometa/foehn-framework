---
layout: home

hero:
  name: WP Tempest
  text: Modern WordPress Development
  tagline: Attribute-based auto-discovery for hooks, post types, blocks, and more
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/studiometa/wp-tempest

features:
  - icon: 🚀
    title: Zero Configuration
    details: Auto-discovery of components via PHP 8 attributes. No registration boilerplate.
  - icon: 🎯
    title: Modern DX
    details: Type-safe, IDE-friendly, testable code with dependency injection.
  - icon: 🔌
    title: WordPress Native
    details: Works seamlessly with Timber, ACF, Gutenberg, and Full Site Editing.
  - icon: ⚡
    title: Minimal Boilerplate
    details: One line to boot your theme, attributes handle the rest.
---

## Quick Example

```php
<?php
// app/Hooks/ThemeHooks.php

namespace App\Hooks;

use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }
}
```

## Acknowledgements

WP Tempest stands on the shoulders of giants. We're grateful to the following projects and their maintainers:

### Core Dependencies

- **[Tempest Framework](https://github.com/tempestphp/tempest-framework)** by Brent Roose — The discovery-first PHP framework that powers WP Tempest's attribute-based auto-discovery and dependency injection
- **[Timber](https://github.com/timber/timber)** by Upstatement — The incredible library that brings Twig templating to WordPress

### Inspirations

- **[Acorn](https://github.com/roots/acorn)** by Roots — Pioneered Laravel-style development in WordPress and inspired our approach to modern WordPress DX
- **[Symfony](https://symfony.com/)** — The `#[AsEventListener]` and other attributes inspired our hook registration syntax

### Tools

- **[ACF Builder](https://github.com/StoutLogic/acf-builder)** by StoutLogic — Fluent PHP API for defining ACF fields
- **[Pest](https://pestphp.com/)** — Elegant testing framework used for our test suite
- **[Mago](https://github.com/carthage-software/mago)** — PHP toolchain for linting and formatting

<style>
:root {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: -webkit-linear-gradient(120deg, #bd34fe 30%, #41d1ff);
}
</style>
