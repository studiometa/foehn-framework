<?php

declare(strict_types=1);

/**
 * A settings page field whose option a `pre_option_{$option}` filter sets.
 *
 * The fixture class goes through `SettingsPageDiscovery` as a theme class does, so
 * `admin_menu`, `add_settings_field()` and `do_settings_sections()` are the real
 * ones. The filters stand for a package config that wins over the option.
 *
 * Run by `SettingsReadOnlyTest` through `wp eval-file`. One line of JSON, because
 * `Site::wp()` hands back the last line of a command.
 */

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\SettingField;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

#[AsSettingsPage(slug: 'smoke-settings', title: 'Smoke settings', parent: 'options-general.php')]
final readonly class SmokeReadOnlySettings implements SettingsInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array
    {
        return [
            'smoke_forced_token' => Setting::string(label: 'Token'),
            'smoke_forced_flag' => Setting::bool(label: 'Flag'),
            'smoke_free_text' => Setting::string(label: 'Free'),
        ];
    }
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);

$discovery = new SettingsPageDiscovery(new DiscoveryProblems());
$discovery->discover(new DiscoveryLocation('Smoke\\', __DIR__), new ClassReflector(SmokeReadOnlySettings::class));
$discovery->apply();

add_filter('pre_option_smoke_forced_token', static fn(): string => 'from-config');
add_filter('pre_option_smoke_forced_flag', static fn(): string => '1');

do_action('admin_menu');

ob_start();
do_settings_sections('smoke-settings');
$html = (string) ob_get_clean();

/**
 * The `<input>` tag of an option.
 */
function smokeReadOnlyInput(string $html, string $option): string
{
    preg_match('/<input[^>]*name="' . preg_quote($option, '/') . '"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

$token = smokeReadOnlyInput($html, 'smoke_forced_token');
$flag = smokeReadOnlyInput($html, 'smoke_forced_flag');
$free = smokeReadOnlyInput($html, 'smoke_free_text');

echo wp_json_encode([
    'token' => [
        'value' => str_contains($token, 'value="from-config"'),
        'readonly' => str_contains($token, 'readonly'),
    ],
    'flag' => [
        'checked' => str_contains($flag, 'checked'),
        'disabled' => str_contains($flag, 'disabled'),
    ],
    'free' => [
        'found' => $free !== '',
        'locked' => str_contains($free, 'readonly') || str_contains($free, 'disabled'),
    ],
    'note' => substr_count($html, SettingField::READONLY_NOTE),
]);
