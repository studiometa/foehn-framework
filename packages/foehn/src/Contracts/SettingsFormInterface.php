<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * A settings page that builds its own form body.
 *
 * Optional, and the alternative to naming a `template` on #[AsSettingsPage]. A
 * page needs one or the other: without either there is nothing between the
 * heading and the submit button.
 *
 * Reach for it when the form needs more than the page's own values — a list of
 * post types to choose from, a value fetched from somewhere. The page is
 * resolved from the container, so a `ViewEngineInterface` in its constructor is
 * all it takes to render Twig here too:
 *
 * ```php
 * public function form(): string
 * {
 *     return $this->view->render('settings/theme-settings', [
 *         'settings' => Settings::all(),
 *         'post_types' => get_post_types(['public' => true]),
 *     ]);
 * }
 * ```
 */
interface SettingsFormInterface
{
    /**
     * The body of the form.
     *
     * Returned rather than echoed, like TemplateControllerInterface::handle(),
     * so a page composes it however it likes and the framework decides where it
     * goes. It sits between `do_settings_sections()` and the submit button, so
     * it prints fields and nothing else.
     */
    public function form(): string;
}
