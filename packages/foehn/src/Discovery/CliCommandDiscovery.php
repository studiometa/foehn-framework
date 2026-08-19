<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_CLI;

/**
 * Discovers CLI commands and registers them with WP-CLI.
 */
final class CliCommandDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Discover CLI command attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        if (!$class->hasAttribute(AsCliCommand::class)) {
            return;
        }

        // Checked before the attribute is built: a class carrying #[AsCliCommand]
        // without the interface is a mistake, and building its attribute first
        // would report that mistake as an argument error from somewhere else.
        if (!$class->implements(CliCommandInterface::class)) {
            return;
        }

        $this->addItem($location, [
            'attribute' => $class->getAttribute(AsCliCommand::class),
            'className' => $class->getName(),
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
            /** @var AsCliCommand $attribute */
            $attribute = $item['attribute'];

            $this->registerCommand($attribute, $item['className']);
        }
    }

    /**
     * Register a single command with WP-CLI.
     *
     * @param class-string<CliCommandInterface> $className
     */
    private function registerCommand(AsCliCommand $attribute, string $className): void
    {
        $container = $this->container;

        // Create wrapper callback for WP-CLI
        $callback = static function (array $args, array $assocArgs) use ($container, $className): void {
            /** @var CliCommandInterface $command */
            $command = $container->get($className);
            $command($args, $assocArgs);
        };

        // Build WP-CLI command name with 'tempest' namespace
        $commandName = 'tempest ' . $attribute->name;

        // Register with WP-CLI
        WP_CLI::add_command($commandName, $callback, [
            'shortdesc' => $attribute->description,
            'longdesc' => $attribute->longDescription,
        ]);
    }
}
