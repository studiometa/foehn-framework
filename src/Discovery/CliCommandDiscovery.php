<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\WpCli;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryContext;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use WP_CLI;

/**
 * Discovers CLI commands and registers them with WP-CLI.
 */
final class CliCommandDiscovery implements Discovery
{
    use IsDiscovery;

    public const string CACHE_KEY = 'cli_commands';

    public function __construct(
        private readonly Container $container,
    ) {}

    public function discover(DiscoveryContext $context, ClassReflector $class): ?DiscoveryItems
    {
        $attribute = $class->getAttribute(AsCliCommand::class);

        if ($attribute === null) {
            return null;
        }

        if (!$class->implements(CliCommandInterface::class)) {
            return null;
        }

        return DiscoveryItems::create($this)->add(self::CACHE_KEY, [$class->getName(), $attribute]);
    }

    public function apply(): void
    {
        // Only register if WP-CLI is available
        if (!WpCli::isAvailable()) {
            return;
        }

        $items = $this->discoveryItems->get(self::CACHE_KEY);

        foreach ($items as [$className, $attribute]) {
            $this->registerCommand($className, $attribute);
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
