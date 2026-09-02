<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Admin;

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Cron\Heartbeat;
use Studiometa\Foehn\Helpers\Env;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Store;

/**
 * One admin page saying what state this installation is actually in.
 *
 * The questions it answers are the ones asked at the worst moment: a page is stale, or a
 * scheduled job did not run, and the person looking has a browser and no shell. Every
 * value on it is resolved rather than configured — the environment as
 * {@see Env} reports it, the cache as it is on disk right now — because a dashboard that
 * echoed the config file back would agree with the config file exactly when the config
 * file is the problem.
 *
 * **It is not a settings page and deliberately not built as one.** No
 * `#[AsSettingsPage]`, no `register_setting()`, no form the browser can change anything
 * with except the two clear buttons. Nothing here is stored: it reads, and it posts to
 * {@see CacheActions}.
 *
 * **It is the no-JavaScript path.** The admin bar's controls need a script to turn a menu
 * item into a POST; these two buttons are plain HTML forms and work with scripting off,
 * which is what makes them the ones an operator can rely on.
 *
 * Formatting comes from WordPress — `size_format()` and `human_time_diff()` — rather than
 * from a copy of `wp foehn cache:status`'s. That command reports readers and snippet
 * hashes for somebody with a shell; this reports state to somebody without one, and the
 * two having one formatter between them would give neither what it needs.
 */
final readonly class Dashboard
{
    public function __construct(
        private PageCacheConfig $config,
        private Store $store,
        private Heartbeat $heartbeat,
    ) {}

    public function register(): void
    {
        add_action('admin_menu', $this->addPage(...));
    }

    /**
     * Add the top-level page.
     *
     * Top-level rather than under Tools, because the admin bar's node points at it and a
     * control an operator has to go looking for is one they will not find in an incident.
     */
    public function addPage(): void
    {
        add_menu_page(
            __('Føhn', 'foehn'),
            __('Føhn', 'foehn'),
            CacheActions::CAPABILITY,
            CacheActions::PAGE,
            $this->render(...),
            'dashicons-cloud',
            80,
        );
    }

    /**
     * Render the page.
     *
     * `add_menu_page()` already refused the capability, and this checks it again: the
     * callback is a public method on a container singleton, so "only reachable through the
     * menu" is a property of today's wiring rather than of this class.
     */
    public function render(): void
    {
        if (!current_user_can(CacheActions::CAPABILITY)) {
            return;
        }

        $stats = $this->store->stats();
        $sections = $this->store->sectionStats();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Føhn', 'foehn') . '</h1>';

        $this->renderNotice();

        echo '<h2>' . esc_html__('Environment', 'foehn') . '</h2>';
        echo '<table class="widefat striped"><tbody>';
        // Labelled with the constant names, not with prose: these are the two values a
        // report from a colleague has to be able to name unambiguously.
        $this->renderRow('WP_ENVIRONMENT_TYPE', Env::get());
        $this->renderRow('WP_DEBUG', $this->yesNo(Env::isDebug()));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Page cache', 'foehn') . '</h2>';
        echo '<table class="widefat striped"><tbody>';
        $this->renderRow(__('Configured', 'foehn'), $this->yesNo($this->config->enabled));
        $this->renderRow(__('Effective', 'foehn'), $this->effectiveState());
        $this->renderRow(__('Cache path', 'foehn'), $this->store->root());
        $this->renderRow(__('TTL', 'foehn'), $this->ttl());
        $this->renderRow(__('Cached responses', 'foehn'), (string) $stats['files']);
        $this->renderRow(__('Section responses', 'foehn'), (string) $sections['files']);
        $this->renderRow(__('Total size', 'foehn'), $this->size($stats['bytes']));
        $this->renderRow(__('Last full purge', 'foehn'), $this->ago($this->lastFlush()));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('WP-Cron', 'foehn') . '</h2>';
        echo '<table class="widefat striped"><tbody>';
        // "Never" until the Docker cron runner ships and writes the option — see
        // {@see Heartbeat}. That is the same answer a broken runner gives, which is the
        // point of showing it at all.
        $this->renderRow(__('Last real run', 'foehn'), $this->ago($this->heartbeat->recordedAt()));
        echo '</tbody></table>';

        $this->renderForms();

        echo '</div>';
    }

    /**
     * The two buttons.
     *
     * Two forms rather than one with two submits: each carries a nonce minted for its own
     * action, which is what stops the token on "clear everything" from authorising
     * anything else. Plain POST, no script.
     */
    private function renderForms(): void
    {
        echo '<h2>' . esc_html__('Clear', 'foehn') . '</h2>';
        echo
            '<p>'
                . esc_html__(
                    'Clearing works even while the page cache is switched off, so files an earlier release left behind can still be removed.',
                    'foehn',
                )
                . '</p>'
        ;

        foreach ([
            CacheActions::FLUSH => __('Clear the whole page cache', 'foehn'),
            CacheActions::FLUSH_SECTIONS => __('Clear the section cache only', 'foehn'),
        ] as $action => $label) {
            echo
                '<form method="post" action="'
                    . esc_url(CacheActions::endpoint())
                    . '" style="display:inline-block;margin-right:.5em">'
            ;
            echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
            wp_nonce_field(CacheActions::nonceAction($action));
            echo '<button type="submit" class="button">' . esc_html($label) . '</button>';
            echo '</form>';
        }
    }

    /**
     * What the last action did, when the browser has just come back from one.
     *
     * Read from the two query args {@see CacheActions} attaches, and nothing else: the
     * result is matched against the fixed codes rather than printed, and the count goes
     * through `absint()`. A notice assembled from a query string is a cross-site scripting
     * hole in an admin page, which is the most valuable place to have one.
     */
    private function renderNotice(): void
    {
        $result = $_GET[CacheActions::RESULT_ARG] ?? null;

        if (!is_string($result)) {
            return;
        }

        $removed = absint($_GET[CacheActions::COUNT_ARG] ?? 0);

        if ($result === CacheActions::CLEARED) {
            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %d is a number of cached responses. */
                    _n('%d cached response removed.', '%d cached responses removed.', $removed, 'foehn'),
                    $removed,
                )),
            );

            return;
        }

        if ($result !== CacheActions::FAILED) {
            return;
        }

        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__(
            'Nothing was cleared: that page has no address this cache can key.',
            'foehn',
        ));
    }

    private function renderRow(string $label, string $value): void
    {
        printf('<tr><th scope="row">%s</th><td><code>%s</code></td></tr>', esc_html($label), esc_html($value));
    }

    /**
     * Whether the cache is doing anything, which is not the same as whether it is on.
     *
     * The third state is the one worth a row of its own: `enabled: true` in an environment
     * the config does not list means nothing is written and nothing is served, and a
     * dashboard that reported only "enabled" would send somebody looking for a broken
     * cache rather than for a missing environment name.
     */
    private function effectiveState(): string
    {
        if (!$this->config->enabled) {
            return __('Disabled', 'foehn');
        }

        if (!$this->config->allowsEnvironment()) {
            return sprintf(
                /* translators: 1: environment name, 2: comma-separated list of environments. */
                __('Enabled, but unavailable in %1$s — allowed: %2$s', 'foehn'),
                Env::get(),
                implode(', ', $this->config->environments),
            );
        }

        return __('Active', 'foehn');
    }

    private function ttl(): string
    {
        return $this->config->ttl > 0
            ? sprintf(__('%d seconds', 'foehn'), $this->config->ttl)
            : __('None — purge-driven', 'foehn');
    }

    /**
     * The timestamp {@see Invalidator::flush()} recorded, or null when nobody has.
     */
    private function lastFlush(): ?int
    {
        $recorded = get_option(Invalidator::LAST_FLUSH_OPTION);

        return is_numeric($recorded) ? (int) $recorded : null;
    }

    /**
     * A timestamp as an age, in WordPress's own words.
     */
    private function ago(?int $timestamp): string
    {
        if ($timestamp === null) {
            return __('Never', 'foehn');
        }

        $now = time();

        return $timestamp >= $now
            ? __('Just now', 'foehn')
            : sprintf(__('%s ago', 'foehn'), human_time_diff($timestamp, $now));
    }

    /**
     * A byte count in WordPress's own words.
     *
     * `size_format()` answers `false` for anything it will not format, and an empty cache
     * is the common case here — so the fallback is the honest reading of "no bytes"
     * rather than a blank cell.
     */
    private function size(int $bytes): string
    {
        $formatted = size_format($bytes);

        return is_string($formatted) ? $formatted : '0 B';
    }

    private function yesNo(bool $value): string
    {
        return $value ? __('Yes', 'foehn') : __('No', 'foehn');
    }
}
