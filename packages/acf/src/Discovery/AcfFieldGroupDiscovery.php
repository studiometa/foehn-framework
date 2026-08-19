<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsAcfFieldGroup] attribute
 * and registers them as ACF field groups.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class AcfFieldGroupDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover ACF field group attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsAcfFieldGroup::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements AcfFieldGroupInterface
        if (!$class->implements(AcfFieldGroupInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsAcfFieldGroup]',
                $class->getName(),
                AcfFieldGroupInterface::class,
            ));
        }

        $this->addItem($location, [
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
            foreach ($this->getItems() as $item) {
                /** @var AsAcfFieldGroup $attribute */
                $attribute = $item['attribute'];

                $this->registerFieldGroup($attribute, $item['className']);
            }
        });
    }

    /**
     * Register a single ACF field group.
     *
     * @param class-string<AcfFieldGroupInterface> $className
     */
    private function registerFieldGroup(AsAcfFieldGroup $attribute, string $className): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        if (!method_exists($className, 'fields')) {
            return;
        }

        $fields = $className::fields();

        // Parse and set location
        $parsedLocation = $this->parseLocation($attribute->location);
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
        $config['title'] = $attribute->title;
        $config['position'] = $attribute->position;
        $config['menu_order'] = $attribute->menuOrder;
        $config['style'] = $attribute->style;
        $config['label_placement'] = $attribute->labelPlacement;
        $config['instruction_placement'] = $attribute->instructionPlacement;

        if ($attribute->hideOnScreen !== []) {
            $config['hide_on_screen'] = $attribute->hideOnScreen;
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

        return is_array($firstRule) && ($firstRule['param'] ?? null) !== null;
    }
}
