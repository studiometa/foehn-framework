<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks;

use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Ui\Extension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Register studiometa/ui's Twig namespaces when the package is installed.
 *
 * `studiometa/ui` ships its components as Twig templates and reaches them through
 * namespaces — `@ui/Button/Button.twig`, `@svg/…` — which only exist once its
 * extension has been handed the Twig loader. That is why it cannot be registered
 * through `#[AsTwigExtension]` like the others: the container has no loader to
 * autowire, and the loader only exists once Timber has built the environment.
 *
 * Nothing happens when the package is absent, so the framework gains no dependency
 * — `composer require studiometa/ui` in a project is the whole installation.
 *
 * The package's own extension subclasses the same `studiometa/twig-toolkit`
 * extension the framework already registers. Twig keys functions by name and lets
 * the later registration win, so the two coexist rather than collide.
 *
 * Opt in from the theme, like every other hook class the framework ships:
 *
 * ```php
 * // app/foehn.config.php
 * return new FoehnConfig(hooks: [StudiometaUi::class]);
 * ```
 *
 * Framework hook classes are opt-in by design — registering one because it happens
 * to sit in a scanned package would let a `composer update` change what a site
 * does. Adding a Twig namespace is harmless, but the rule is worth more than the
 * exception.
 *
 * A project that wants to override a component ships its own file and adds a path
 * ahead of the package's, on this same filter at a later priority:
 *
 * ```php
 * #[AsFilter('timber/twig', priority: 20)]
 * public function overrideUi(Environment $twig): Environment
 * {
 *     $twig->getLoader()->prependPath(get_template_directory() . '/templates/ui', 'ui');
 *
 *     return $twig;
 * }
 * ```
 */
final class StudiometaUi
{
    #[AsFilter('timber/twig')]
    public function register(Environment $twig): Environment
    {
        if (!class_exists(Extension::class)) {
            return $twig;
        }

        $loader = $twig->getLoader();

        // The namespaces are registered on the loader itself, so a project using a
        // loader chain rather than the filesystem gets nothing to break.
        if (!$loader instanceof FilesystemLoader) {
            return $twig;
        }

        $twig->addExtension(new Extension($loader));

        return $twig;
    }
}
