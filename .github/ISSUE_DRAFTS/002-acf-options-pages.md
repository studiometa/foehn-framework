# Add `#[AsAcfOptionsPage]` attribute for ACF options pages

## Problem

Every analyzed Studio Meta project uses ACF options pages for global theme settings:

- Footer content
- Social links
- Contact information
- Global banners/alerts
- Maintenance mode settings
- API keys and integrations

Currently this requires manual registration in `ACFManager`:

```php
// Current pattern
class ACFManager implements ManagerInterface {
    public function run() {
        add_action('acf/init', [$this, 'register_acf_options_page']);
    }

    public function register_acf_options_page() {
        acf_add_options_page([
            'page_title' => 'Theme General Settings',
            'menu_title' => 'Theme Settings',
            'menu_slug' => 'theme-general-settings',
            'capability' => 'edit_posts',
            'redirect' => false,
        ]);

        // Sub-pages
        acf_add_options_sub_page([
            'page_title' => 'Footer Settings',
            'menu_title' => 'Footer',
            'parent_slug' => 'theme-general-settings',
        ]);
    }
}

// Then fields are registered separately in ACF/Groups/OptionsPage.php
class OptionsPage extends AbstractGroup {
    public static function register_blocks() {
        $options = new FieldsBuilder('theme_options', [...]);
        $options
            ->addTab('Header')
            ->addRepeater('header_links', [...])
            ->addTab('Footer')
            ->addWysiwyg('footer_text')
            ->addRepeater('social_links', [...]);

        $options->setLocation('options_page', '==', 'theme-general-settings');
        acf_add_local_field_group($options->build());
    }
}
```

## Proposed solution

Combine options page registration AND fields in a single class:

```php
// app/Fields/Options/ThemeSettings.php
namespace App\Fields\Options;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfOptionsPage(
    title: 'Theme Settings',
    menuTitle: 'Theme Settings',
    slug: 'theme-settings',
    capability: 'edit_posts',
    icon: 'dashicons-admin-generic',
    position: 80,
)]
final class ThemeSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('theme_settings');

        return $builder
            ->addTab('Header')
            ->addRepeater('header_links', [
                'label' => 'Header Links',
                'button_label' => 'Add Link',
            ])
                ->addLink('link')
            ->endRepeater()

            ->addTab('Footer')
            ->addWysiwyg('footer_text', ['label' => 'Footer Text'])
            ->addRepeater('social_links', ['label' => 'Social Links'])
                ->addSelect('platform', [
                    'choices' => [
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'linkedin' => 'LinkedIn',
                        'twitter' => 'X (Twitter)',
                    ],
                ])
                ->addUrl('url')
            ->endRepeater()

            ->addTab('Contact')
            ->addEmail('contact_email')
            ->addText('contact_phone')
            ->addTextarea('contact_address')

            ->addTab('Maintenance')
            ->addTrueFalse('maintenance_mode', [
                'label' => 'Enable Maintenance Mode',
                'default_value' => false,
            ])
            ->addWysiwyg('maintenance_message');
    }
}
```

## Sub-pages support

```php
#[AsAcfOptionsPage(
    title: 'Footer Settings',
    menuTitle: 'Footer',
    slug: 'theme-settings-footer',
    parent: 'theme-settings',  // Parent page slug
)]
final class FooterSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder { ... }
}
```

## Attribute definition

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfOptionsPage
{
    /**
     * @param string $title Page title
     * @param string|null $menuTitle Menu title (defaults to $title)
     * @param string $slug Menu slug
     * @param string $capability Required capability
     * @param string|null $icon Dashicon or URL (null for sub-pages)
     * @param int|null $position Menu position (null for sub-pages)
     * @param string|null $parent Parent page slug for sub-pages
     * @param bool $redirect Redirect to first sub-page
     * @param bool $autoload Autoload options for performance
     */
    public function __construct(
        public string $title,
        public ?string $menuTitle = null,
        public string $slug,
        public string $capability = 'edit_posts',
        public ?string $icon = null,
        public ?int $position = null,
        public ?string $parent = null,
        public bool $redirect = false,
        public bool $autoload = true,
    ) {}
}
```

## Helper service for retrieving options

Foehn should provide an `OptionsService` that wraps `get_field('field', 'options')`:

```php
// Built-in service
final class AcfOptionsService
{
    private array $cache = [];

    public function get(string $field, mixed $default = null): mixed
    {
        if (!isset($this->cache[$field])) {
            $this->cache[$field] = get_field($field, 'options');
        }

        return $this->cache[$field] ?? $default;
    }

    public function all(): array
    {
        if (!isset($this->cache['__all__'])) {
            $this->cache['__all__'] = get_fields('options') ?: [];
        }

        return $this->cache['__all__'];
    }
}

// Usage in ContextProvider
#[AsContextProvider('*')]
final readonly class GlobalContext implements ContextProviderInterface
{
    public function __construct(
        private AcfOptionsService $options,
    ) {}

    public function provide(array $context): array
    {
        return array_merge($context, [
            'social_links' => $this->options->get('social_links', []),
            'footer_text' => $this->options->get('footer_text'),
            'contact' => [
                'email' => $this->options->get('contact_email'),
                'phone' => $this->options->get('contact_phone'),
            ],
        ]);
    }
}
```

## CLI command

```bash
php foehn make:options-page ThemeSettings
php foehn make:options-page FooterSettings --parent=theme-settings
```

## Tasks

- [ ] Create `AsAcfOptionsPage` attribute
- [ ] Create `AcfOptionsPageInterface`
- [ ] Create `AcfOptionsPageDiscovery`
- [ ] Create `AcfOptionsService` helper
- [ ] Add `make:options-page` CLI command
- [ ] Add tests
- [ ] Document in README

## Labels

`enhancement`, `acf`, `priority-high`
