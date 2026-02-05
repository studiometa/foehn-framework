# Add `#[AsAcfFieldGroup]` attribute for non-block ACF field groups

## Problem

In real-world WordPress projects, ACF Field Groups are used extensively for:

- Custom post type fields (e.g., `Property.php` with 200+ lines of fields)
- Page-specific fields (e.g., `FrontPage.php`, `FAQPage.php`)
- Taxonomy term fields (e.g., `CategoryPage.php`, `RoomType.php`)
- Attachment fields

Currently, Foehn only supports `#[AsAcfBlock]` for ACF blocks, but **100% of analyzed projects** have a `ACF/Groups/` directory with field group definitions that have no equivalent in Foehn.

## Current pattern (wp-toolkit)

```php
// app/ACF/Groups/Property.php
namespace App\ACF\Groups;

class Property extends AbstractGroup {
    public static function register_blocks() {
        $property = new FieldsBuilder('property', [
            'title' => __('Property Details', 'theme'),
            'menu_order' => 0,
            'position' => 'acf_after_title',
        ]);

        $property
            ->addText('external_id', ['label' => __('External ID', 'theme')])
            ->addTab('General Info')
            ->addFields(Wysiwyg::get_field('intro'))
            ->addRepeater('features_list', [...])
            // ... 150+ more lines
            ;

        self::set_location($property);
        acf_add_local_field_group($property->build());
    }

    protected static function set_location(FieldsBuilder $block) {
        $block->setLocation('post_type', '==', 'property');
    }
}

// Then manually registered in ACFManager:
public function register_acf_fields() {
    Property::register_blocks();
    FrontPage::register_blocks();
    // ... etc
}
```

## Proposed solution

```php
// app/Fields/PropertyFields.php
namespace App\Fields;

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfFieldGroup(
    name: 'property_fields',
    title: 'Property Details',
    location: ['post_type' => 'property'],
    position: 'acf_after_title',
    menuOrder: 0,
)]
final class PropertyFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('property_fields');

        return $builder
            ->addText('external_id', ['label' => __('External ID', 'theme')])
            ->addTab('General Info')
            ->addWysiwyg('intro', ['label' => 'Introduction'])
            ->addRepeater('features_list', [...]);
    }
}
```

## Attribute definition

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfFieldGroup
{
    /**
     * @param string $name Unique field group name
     * @param string $title Display title in admin
     * @param array<string, mixed> $location Location rules (simplified or full ACF format)
     * @param string $position Position: 'acf_after_title', 'normal', 'side'
     * @param int $menuOrder Order in admin
     * @param string $style Style: 'default', 'seamless'
     * @param string $labelPlacement Label placement: 'top', 'left'
     * @param string $instructionPlacement Instruction placement: 'label', 'field'
     * @param string[] $hideOnScreen Elements to hide: 'the_content', 'excerpt', etc.
     */
    public function __construct(
        public string $name,
        public string $title,
        public array $location,
        public string $position = 'normal',
        public int $menuOrder = 0,
        public string $style = 'default',
        public string $labelPlacement = 'top',
        public string $instructionPlacement = 'label',
        public array $hideOnScreen = [],
    ) {}
}
```

## Location syntax options

Support both simplified and full ACF location syntax:

```php
// Simplified (common cases)
#[AsAcfFieldGroup(location: ['post_type' => 'product'])]
#[AsAcfFieldGroup(location: ['page_template' => 'page-faq.php'])]
#[AsAcfFieldGroup(location: ['taxonomy' => 'product_category'])]
#[AsAcfFieldGroup(location: ['options_page' => 'theme-settings'])]

// Full ACF format (complex rules)
#[AsAcfFieldGroup(location: [
    [
        ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
        ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
    ],
    [
        ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
    ],
])]
```

## Interface

```php
interface AcfFieldGroupInterface
{
    /**
     * Define ACF fields for this group.
     */
    public static function fields(): FieldsBuilder;
}
```

## Discovery

Create `AcfFieldGroupDiscovery` that:

1. Discovers classes with `#[AsAcfFieldGroup]`
2. Validates they implement `AcfFieldGroupInterface`
3. Registers field groups on `acf/init` hook

## CLI command

```bash
php foehn make:field-group PropertyFields --post-type=property
php foehn make:field-group FrontPageFields --page-template=front-page
php foehn make:field-group CategoryFields --taxonomy=category
```

## Suggested file location convention

```
app/
├── Fields/
│   ├── PostType/
│   │   ├── PropertyFields.php
│   │   └── ProductFields.php
│   ├── Page/
│   │   ├── FrontPageFields.php
│   │   └── FAQPageFields.php
│   ├── Taxonomy/
│   │   └── CategoryFields.php
│   └── Options/
│       └── ThemeSettingsFields.php  # See #[AsAcfOptionsPage] issue
```

## Tasks

- [ ] Create `AsAcfFieldGroup` attribute
- [ ] Create `AcfFieldGroupInterface`
- [ ] Create `AcfFieldGroupDiscovery`
- [ ] Add location syntax parser (simplified → full ACF format)
- [ ] Add `make:field-group` CLI command
- [ ] Add tests
- [ ] Document in README

## Labels

`enhancement`, `acf`, `priority-high`
