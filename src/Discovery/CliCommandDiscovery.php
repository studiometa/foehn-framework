<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;
use WP_CLI;

/**
 * Discovers CLI commands and registers them with WP-CLI.
 */
final class CliCommandDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Discover CLI command attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsCliCommand::class);

        if ($attributes === []) {
            return;
        }

        if (!$class->implementsInterface(CliCommandInterface::class)) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'className' => $class->getName(),
            'name' => $attribute->name,
            'description' => $attribute->description,
            'longDescription' => $attribute->longDescription,
        ]);
    }

    /**
     * Apply discovered CLI commands.
     */
    public function apply(): void
    {
        // Only register if WP-CLI is available
        if (!WpCli::isAvailable()) {
            return;
        }

        foreach ($this->getItems() as $item) {
            $this->doRegisterCommand($item['className'], $item['name'], $item['description'], $item['longDescription']);
        }
    }

    /**
     * Actually register the command with WP-CLI.
     *
     * @param class-string<CliCommandInterface> $className
     */
    private function doRegisterCommand(
        string $className,
        string $name,
        string $description,
        ?string $longDescription,
    ): void {
        $container = $this->container;

        // Create wrapper callback for WP-CLI
        $callback = static function (array $args, array $assocArgs) use ($container, $className): void {
            /** @var CliCommandInterface $command */
            $command = $container->get($className);
            $command($args, $assocArgs);
        };

        // Build WP-CLI command name with 'tempest' namespace
        $commandName = 'tempest ' . $name;

        // Register with WP-CLI
        WP_CLI::add_command($commandName, $callback, [
            'shortdesc' => $description,
            'longdesc' => $longDescription,
        ]);
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        return [
            'className' => $item['className'],
            'name' => $item['name'],
            'description' => $item['description'],
            'longDescription' => $item['longDescription'],
        ];
    }
}
