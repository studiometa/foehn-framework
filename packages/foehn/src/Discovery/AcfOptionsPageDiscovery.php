<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;

/**
 * Discovers classes marked with #[AsAcfOptionsPage] attribute
 * and registers them as ACF Options Pages.
 */
final class AcfOptionsPageDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover ACF options page attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsAcfOptionsPage::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'hasFields' => $class->implementsInterface(AcfOptionsPageInterface::class),
        ]);
    }

    /**
     * Apply discovered ACF options pages by registering them.
     */
    public function apply(): void
    {
        // ACF options pages must be registered on acf/init
        add_action('acf/init', function (): void {
            foreach ($this->getItems() as $item) {
                $this->registerOptionsPage($item);
            }
        });
    }

    /**
     * Register a single ACF options page.
     *
     * @param array<string, mixed> $item
     */
    private function registerOptionsPage(array $item): void
    {
        $attribute = $this->resolveAttribute($item);
        $className = $item['className'];
        $hasFields = $item['hasFields'];

        // Build options page configuration
        $config = [
            'page_title' => $attribute->pageTitle,
            'menu_title' => $attribute->getMenuTitle(),
            'menu_slug' => $attribute->getMenuSlug(),
            'capability' => $attribute->capability,
            'redirect' => $attribute->redirect,
            'autoload' => $attribute->autoload,
            'post_id' => $attribute->getPostId(),
        ];

        // Add optional configuration
        if ($attribute->position !== null) {
            $config['position'] = $attribute->position;
        }

        if ($attribute->parentSlug !== null) {
            $config['parent_slug'] = $attribute->parentSlug;
        }

        if ($attribute->iconUrl !== null) {
            $config['icon_url'] = $attribute->iconUrl;
        }

        if ($attribute->updateButton !== null) {
            $config['update_button'] = $attribute->updateButton;
        }

        if ($attribute->updatedMessage !== null) {
            $config['updated_message'] = $attribute->updatedMessage;
        }

        // Register the options page
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        $registerFunction = $attribute->isSubPage() ? 'acf_add_options_sub_page' : 'acf_add_options_page';
        $registerFunction($config);

        // Register fields if the class defines them
        if ($hasFields) {
            $this->registerFields($attribute->getMenuSlug(), $className);
        }
    }

    /**
     * Resolve the AsAcfOptionsPage attribute from a discovered or cached item.
     *
     * @param array<string, mixed> $item
     */
    private function resolveAttribute(array $item): AsAcfOptionsPage
    {
        if (isset($item['attribute'])) {
            return $item['attribute'];
        }

        // Cached format - rebuild attribute
        return new AsAcfOptionsPage(
            pageTitle: $item['pageTitle'],
            menuTitle: $item['menuTitle'],
            menuSlug: $item['menuSlug'],
            capability: $item['capability'],
            position: $item['position'],
            parentSlug: $item['parentSlug'],
            iconUrl: $item['iconUrl'],
            redirect: $item['redirect'],
            postId: $item['postId'],
            autoload: $item['autoload'],
            updateButton: $item['updateButton'],
            updatedMessage: $item['updatedMessage'],
        );
    }

    /**
     * Register ACF fields for the options page.
     *
     * @param string $menuSlug
     * @param class-string<AcfOptionsPageInterface> $className
     */
    private function registerFields(string $menuSlug, string $className): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        /** @var AcfOptionsPageInterface $className */
        $fields = $className::fields();

        // Set the location to this options page
        $fields->setLocation('options_page', '==', $menuSlug);

        // Register the field group
        acf_add_local_field_group($fields->build());
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsAcfOptionsPage $attribute */
        $attribute = $item['attribute'];

        return [
            'className' => $item['className'],
            'hasFields' => $item['hasFields'],
            'pageTitle' => $attribute->pageTitle,
            'menuTitle' => $attribute->menuTitle,
            'menuSlug' => $attribute->menuSlug,
            'capability' => $attribute->capability,
            'position' => $attribute->position,
            'parentSlug' => $attribute->parentSlug,
            'iconUrl' => $attribute->iconUrl,
            'redirect' => $attribute->redirect,
            'postId' => $attribute->postId,
            'autoload' => $attribute->autoload,
            'updateButton' => $attribute->updateButton,
            'updatedMessage' => $attribute->updatedMessage,
        ];
    }
}
