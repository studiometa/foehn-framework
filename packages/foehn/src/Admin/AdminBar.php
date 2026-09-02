<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Admin;

use WP_Admin_Bar;
use WP_Post;

/**
 * The three cache clears, one click away from wherever an editor already is.
 *
 * The dashboard is where an operator goes to read state; this is where an editor goes
 * when the page in front of them is wrong. "Clear this page" from the page itself is the
 * action that gets used, and the one whose absence sends people to WP-CLI.
 *
 * **No state changes through a GET.** An admin-bar item is an `<a href>`, and a link that
 * cleared a cache would be cleared by every prefetching browser, every link checker and
 * every crawler with a logged-in session cookie — and there is no nonce that fixes that,
 * because the browser follows the link on the user's behalf. So the items' hrefs are
 * inert and the real request is a hidden POST form in the footer, submitted by a small
 * script bound to the item's id. The dashboard stays the path that needs no script.
 *
 * **The current-item entry appears only when WordPress supplies the id.** A singular
 * front-end request has already resolved its query, and a post edit screen has already
 * loaded the row — both are the server's answer. Anywhere else there is no trustworthy
 * id, so the item is absent rather than disabled or guessed at: an item that appears
 * everywhere and works somewhere is one nobody learns to trust.
 *
 * The script is four lines and inline, because a build step and an asset handle for four
 * lines that only ever run for an administrator would cost more than they save.
 */
final readonly class AdminBar
{
    /** The parent node's id. The children hang off it and the footer script names them. */
    private const NODE = 'foehn-cache';

    public function register(): void
    {
        // 100, so Føhn sits after core's own nodes rather than in the middle of them.
        add_action('admin_bar_menu', $this->addNodes(...), 100);

        // Both footers: the bar is rendered on the front end and in the admin, and the
        // forms have to be in the same document as the items that submit them.
        add_action('wp_footer', $this->renderForms(...));
        add_action('admin_footer', $this->renderForms(...));
    }

    /**
     * Add the Føhn Cache node and its items.
     */
    public function addNodes(WP_Admin_Bar $bar): void
    {
        if (!current_user_can(CacheActions::CAPABILITY)) {
            return;
        }

        $bar->add_node([
            'id' => self::NODE,
            'title' => __('Føhn Cache', 'foehn'),
            // The one href here that goes anywhere, and it navigates rather than mutates.
            'href' => CacheActions::dashboardUrl(),
        ]);

        foreach ($this->items() as $action => $label) {
            $bar->add_node([
                'id' => self::NODE . '-' . $action,
                'parent' => self::NODE,
                'title' => $label,
                // Inert on purpose. The footer script cancels the click and posts the
                // matching form instead; without the script the item does nothing, which
                // is the correct failure for a control that must not fire by accident.
                'href' => '#',
            ]);
        }
    }

    /**
     * The hidden forms, and the script that submits them.
     *
     * Rendered from the footer rather than beside the bar, because the bar's markup is
     * core's and a `<form>` inside a `<li>` in it is a nesting nobody should rely on.
     */
    public function renderForms(): void
    {
        if (!current_user_can(CacheActions::CAPABILITY)) {
            return;
        }

        $items = $this->items();
        $postId = $this->trustworthyPostId();

        echo '<div id="' . esc_attr(self::NODE) . '-forms" style="display:none">';

        foreach (array_keys($items) as $action) {
            echo
                '<form method="post" id="'
                    . esc_attr(self::formId($action))
                    . '" action="'
                    . esc_url(CacheActions::endpoint())
                    . '">'
            ;
            echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';

            if ($action === CacheActions::FORGET_POST && $postId !== null) {
                echo
                    '<input type="hidden" name="'
                        . esc_attr(CacheActions::POST_ID_FIELD)
                        . '" value="'
                        . esc_attr((string) $postId)
                        . '">'
                ;
            }

            // Each form carries a token minted for its own action, so the markup of one
            // cannot be replayed as another.
            wp_nonce_field(CacheActions::nonceAction($action));
            echo '</form>';
        }

        echo '</div>';

        $this->renderScript(array_keys($items));
    }

    /**
     * The script that turns a menu item into a POST.
     *
     * A `submit()` call rather than `requestSubmit()`, which some in-app browsers still
     * lack; nothing here needs the validation the latter would run.
     *
     * @param list<string> $actions
     */
    private function renderScript(array $actions): void
    {
        $map = [];

        foreach ($actions as $action) {
            $map['wp-admin-bar-' . self::NODE . '-' . $action] = self::formId($action);
        }

        printf(
            '<script>(function(m){for(var i in m){(function(a,f){if(a&&f){'
            . "a.addEventListener('click',function(e){e.preventDefault();f.submit();});"
            . '}})(document.getElementById(i),document.getElementById(m[i]));}})(%s);</script>',
            (string) wp_json_encode($map),
        );
    }

    /**
     * Which items to show. The current-item one is present only when it can be trusted.
     *
     * @return array<string, string>
     */
    private function items(): array
    {
        $items = [
            CacheActions::FLUSH => __('Clear whole cache', 'foehn'),
            CacheActions::FLUSH_SECTIONS => __('Clear section cache', 'foehn'),
        ];

        if ($this->trustworthyPostId() !== null) {
            $items[CacheActions::FORGET_POST] = __('Clear this page', 'foehn');
        }

        return $items;
    }

    /**
     * The post this request is unambiguously about, or null.
     *
     * Two sources, both the server's own resolution of the request:
     *
     * - **a singular front-end request**, where `get_queried_object()` is the post
     *   WordPress decided to render;
     * - **a post edit screen**, where the global post is the row already loaded.
     *
     * Never `$_GET`. The handler would refuse a bad id anyway, but an item built from the
     * query string is one that appears on a URL somebody crafted, pointing at a post they
     * chose — and the whole reason this control is safe is that the browser never names
     * its target.
     *
     * Filtered to what a visitor could have been served, because a draft has no cached
     * page and {@see CacheActions} refuses it: an item that is always refused is worse
     * than no item.
     */
    private function trustworthyPostId(): ?int
    {
        $post = is_admin() ? $this->editedPost() : $this->queriedPost();

        if (!$post instanceof WP_Post || !is_post_publicly_viewable($post)) {
            return null;
        }

        return $post->ID;
    }

    /**
     * The post a singular front-end request resolved to.
     */
    private function queriedPost(): ?WP_Post
    {
        if (!is_singular()) {
            return null;
        }

        $object = get_queried_object();

        return $object instanceof WP_Post ? $object : null;
    }

    /**
     * The post an edit screen is editing.
     *
     * `get_current_screen()` is only defined once the admin has loaded far enough to have
     * one, and `admin_bar_menu` can fire on screens that never call `set_current_screen()`
     * — so its absence is a "no" rather than an error. `base === 'post'` with an action
     * other than `add` is the edit screen and not the list table or the new-post form,
     * neither of which has a post to clear.
     */
    private function editedPost(): ?WP_Post
    {
        if (!function_exists('get_current_screen')) {
            return null;
        }

        $screen = get_current_screen();

        if ($screen === null || $screen->base !== 'post' || $screen->action === 'add') {
            return null;
        }

        $post = get_post();

        return $post instanceof WP_Post ? $post : null;
    }

    private static function formId(string $action): string
    {
        return self::NODE . '-form-' . $action;
    }
}
