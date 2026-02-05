<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;

/**
 * Discovers classes marked with #[AsAcfFieldGroup] attribute
 * and registers them as ACF field groups.
 */
final class AcfFieldGroupDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover ACF field group attributes on classes.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsAcfFieldGroup::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class implements AcfFieldGroupInterface
        if (!$class->implementsInterface(AcfFieldGroupInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsAcfFieldGroup]',
                $class->getName(),
                AcfFieldGroupInterface::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem([
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered ACF field groups by registering them.
     */
    public function apply(): void
    {
        // ACF field groups must be registered on acf/init
        add_action('acf/init', function (): void {
            foreach ($this->getAllItems() as $item) {
                // Handle cached format
                if (isset($item['name'])) {
                    $this->registerFieldGroupFromCache($item);

                    continue;
                }

                $this->registerFieldGroup($item['attribute'], $item['className']);
            }
        });
    }

    /**
     * Register a single ACF field group.
     *
     * @param AsAcfFieldGroup $attribute
     * @param class-string<AcfFieldGroupInterface> $className
     */
    private function registerFieldGroup(AsAcfFieldGroup $attribute, string $className): void
    {
        $this->doRegisterFieldGroup(
            $className,
            $attribute->name,
            $attribute->title,
            $attribute->location,
            $attribute->position,
            $attribute->menuOrder,
            $attribute->style,
            $attribute->labelPlacement,
            $attribute->instructionPlacement,
            $attribute->hideOnScreen,
        );
    }

    /**
     * Register ACF field group from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerFieldGroupFromCache(array $item): void
    {
        $this->doRegisterFieldGroup(
            $item['className'],
            $item['name'],
            $item['title'],
            $item['location'],
            $item['position'],
            $item['menuOrder'],
            $item['style'],
            $item['labelPlacement'],
            $item['instructionPlacement'],
            $item['hideOnScreen'],
        );
    }

    /**
     * Actually register the ACF field group.
     *
     * @param class-string<AcfFieldGroupInterface> $className
     * @param array<string, mixed> $location
     * @param string[] $hideOnScreen
     */
    private function doRegisterFieldGroup(
        string $className,
        string $name,
        string $title,
        array $location,
        string $position,
        int $menuOrder,
        string $style,
        string $labelPlacement,
        string $instructionPlacement,
        array $hideOnScreen,
    ): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        if (!method_exists($className, 'fields')) {
            return;
        }

        $fields = $className::fields();

        // Parse and set location
        $parsedLocation = $this->parseLocation($location);
        $firstRule = $parsedLocation[0][0];
        $locationBuilder = $fields->setLocation($firstRule['param'], $firstRule['operator'], $firstRule['value']);

        // Add additional location rules if any
        foreach ($parsedLocation as $groupIndex => $group) {
            foreach ($group as $ruleIndex => $rule) {
                // Skip the first rule as it's already set
                if ($groupIndex === 0 && $ruleIndex === 0) {
                    continue;
                }

                // First rule in a new OR group
                if ($ruleIndex === 0) {
                    $locationBuilder = $locationBuilder->or($rule['param'], $rule['operator'], $rule['value']);

                    continue;
                }

                // Additional AND rule
                $locationBuilder = $locationBuilder->and($rule['param'], $rule['operator'], $rule['value']);
            }
        }

        // Build the field group config
        $config = $fields->build();

        // Override settings from attribute
        $config['title'] = $title;
        $config['position'] = $position;
        $config['menu_order'] = $menuOrder;
        $config['style'] = $style;
        $config['label_placement'] = $labelPlacement;
        $config['instruction_placement'] = $instructionPlacement;

        if ($hideOnScreen !== []) {
            $config['hide_on_screen'] = $hideOnScreen;
        }

        // Register the field group
        acf_add_local_field_group($config);
    }

    /**
     * Parse location syntax from simplified to full ACF format.
     *
     * Supports:
     * - Simplified: ['post_type' => 'product']
     * - Full ACF: [[['param' => 'post_type', 'operator' => '==', 'value' => 'product']]]
     *
     * @param array<string, mixed> $location
     * @return array<int, array<int, array{param: string, operator: string, value: string}>>
     */
    public function parseLocation(array $location): array
    {
        // Check if it's already in full ACF format
        if ($this->isFullAcfFormat($location)) {
            /** @var array<int, array<int, array{param: string, operator: string, value: string}>> $location */
            return $location;
        }

        // Convert simplified format to full ACF format
        /** @var array<int, array{param: string, operator: string, value: string}> $rules */
        $rules = [];

        foreach ($location as $param => $value) {
            $rules[] = [
                'param' => $param,
                'operator' => '==',
                'value' => (string) $value,
            ];
        }

        return [$rules];
    }

    /**
     * Check if the location is already in full ACF format.
     *
     * @param array<string, mixed> $location
     */
    private function isFullAcfFormat(array $location): bool
    {
        // Full ACF format is an array of arrays of arrays with 'param' key
        if ($location === []) {
            return false;
        }

        $firstElement = reset($location);

        if (!is_array($firstElement)) {
            return false;
        }

        $firstRule = reset($firstElement);

        return is_array($firstRule) && isset($firstRule['param']);
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsAcfFieldGroup $attribute */
        $attribute = $item['attribute'];

        return [
            'className' => $item['className'],
            'name' => $attribute->name,
            'title' => $attribute->title,
            'location' => $attribute->location,
            'position' => $attribute->position,
            'menuOrder' => $attribute->menuOrder,
            'style' => $attribute->style,
            'labelPlacement' => $attribute->labelPlacement,
            'instructionPlacement' => $attribute->instructionPlacement,
            'hideOnScreen' => $attribute->hideOnScreen,
        ];
    }
}
