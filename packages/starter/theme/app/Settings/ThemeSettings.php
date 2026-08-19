<?php

declare(strict_types=1);

namespace App\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;

/**
 * A settings screen under Appearance, on the WordPress Settings API.
 *
 * `settings()` says what is stored; `render()` says what the form looks like.
 * That separation is the whole difference from an ACF options page, which
 * declares both — and the reason there is no field builder here. Text inputs
 * and checkboxes are a day's work; repeaters, conditional logic and media
 * pickers are ACF's actual product.
 *
 * Everything around the fields — the heading, the form, the nonce, the submit
 * button — comes from the framework, so a page cannot forget the one piece
 * whose absence makes saving fail silently.
 */
#[AsSettingsPage(slug: 'theme-settings', title: 'Theme settings', menuTitle: 'Theme settings', parent: 'themes.php')]
final readonly class ThemeSettings implements SettingsPageInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'starter_contact_email' => Setting::string(sanitize: 'sanitize_email'),
            'starter_show_banner' => Setting::bool(default: false),
            'starter_posts_per_archive' => Setting::int(default: 12),
        ];
    }

    public function render(): void
    { ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="starter_contact_email"><?php esc_html_e('Contact email', 'starter-theme'); ?></label>
                </th>
                <td>
                    <input
                        type="email"
                        id="starter_contact_email"
                        name="starter_contact_email"
                        class="regular-text"
                        value="<?php echo esc_attr((string) Settings::get('starter_contact_email')); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Banner', 'starter-theme'); ?></th>
                <td>
                    <label for="starter_show_banner">
                        <input
                            type="checkbox"
                            id="starter_show_banner"
                            name="starter_show_banner"
                            value="1"
                            <?php checked(Settings::get('starter_show_banner')); ?> />
                        <?php esc_html_e('Show the announcement banner', 'starter-theme'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="starter_posts_per_archive">
                        <?php esc_html_e('Posts per archive', 'starter-theme'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="starter_posts_per_archive"
                        name="starter_posts_per_archive"
                        min="1"
                        value="<?php echo esc_attr((string) Settings::get('starter_posts_per_archive')); ?>" />
                </td>
            </tr>
        </table>
        <?php }
}
