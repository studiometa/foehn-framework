---
layout: home
title: Foehn — Modern WordPress development
titleTemplate: false

hero:
  name: Foehn
  text: Modern WordPress Development
  tagline: Attribute-based auto-discovery for hooks, post types, blocks, and more
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/studiometa/foehn

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

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

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

Foehn stands on the shoulders of giants. We're grateful to the following projects and their maintainers:

### Core Dependencies

- **[Tempest Framework](https://github.com/tempestphp/tempest-framework)** by Brent Roose — The discovery-first PHP framework that powers Foehn's attribute-based auto-discovery and dependency injection
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
  --vp-home-hero-name-background: -webkit-linear-gradient(120deg, #22c55e 30%, #10b981);

  --vp-c-brand-1: #16a34a;
  --vp-c-brand-2: #22c55e;
  --vp-c-brand-3: #4ade80;
}

.dark {
  --vp-c-brand-1: #4ade80;
  --vp-c-brand-2: #22c55e;
  --vp-c-brand-3: #16a34a;
}
</style>
