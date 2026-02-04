<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\WpCli;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use WP_CLI;

/**
 * Discovers CLI commands and registers them with WP-CLI.
 */
final class CliCommandDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsCliCommand::class);

        if ($attribute === null) {
            return;
        }

        if (!$class->implements(CliCommandInterface::class)) {
            return;
        }

        $this->discoveryItems->add($location, [
            'className' => $class->getName(),
            'attribute' => $attribute,
        ]);
    }

    public function apply(): void
    {
        // Only register if WP-CLI is available
        if (!WpCli::isAvailable()) {
            return;
        }

        foreach ($this->discoveryItems as $item) {
            $this->registerCommand($item['className'], $item['attribute']);
        }
    }

    /**
     * Register a command with WP-CLI.
     *
     * @param class-string<CliCommandInterface> $className
     */
    private function registerCommand(string $className, AsCliCommand $attribute): void
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
