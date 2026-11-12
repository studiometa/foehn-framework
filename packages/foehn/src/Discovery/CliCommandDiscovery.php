<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\ProductionGuard;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_CLI;

/**
 * Discovers CLI commands and registers them with WP-CLI.
 *
 * The framework's commands go under `wp foehn`, the project's under `wp app`, or
 * the name FoehnConfig::$cliNamespace gives. A project command can then never
 * collide with one a later Foehn release adds, and `wp app` lists only the site's.
 */
#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class CliCommandDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly Container $container,
        private readonly DiscoveryProblems $problems,
        private readonly FoehnConfig $config,
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
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsCliCommand]',
                sprintf(
                    '%s has #[AsCliCommand] but does not implement %s, so the command is not registered.',
                    $class->getName(),
                    CliCommandInterface::class,
                ),
                sprintf('Declare %s with `implements \\%s`.', $class->getName(), CliCommandInterface::class),
            );

            return;
        }

        $this->addItem($location, [
            'attribute' => $class->getAttribute(AsCliCommand::class),
            'className' => $class->getName(),
            'package' => $this->isPackageLocation($location),
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

            $this->registerCommand($this->commandName($item), $attribute, $item['className']);
        }
    }

    /**
     * The WP-CLI command an item registers, without the leading `wp`.
     *
     * The namespace is resolved here and not stored in the item, so a change of
     * FoehnConfig::$cliNamespace applies without a discovery cache rebuild.
     *
     * @param array<string, mixed> $item
     */
    public function commandName(array $item): string
    {
        /** @var AsCliCommand $attribute */
        $attribute = $item['attribute'];
        $namespace = $item['package'] === true ? 'foehn' : $this->config->cliNamespace;

        return $namespace . ' ' . $attribute->name;
    }

    /**
     * Register a single command with WP-CLI.
     *
     * @param class-string<CliCommandInterface> $className
     */
    private function registerCommand(string $commandName, AsCliCommand $attribute, string $className): void
    {
        $container = $this->container;

        // Create wrapper callback for WP-CLI. A command that writes is checked against
        // the environment before it is built, so no command repeats the check.
        $callback = static function (array $args, array $assocArgs) use ($container, $className, $attribute): void {
            if (!$container->get(ProductionGuard::class)->allows($attribute, $assocArgs)) {
                return;
            }

            /** @var CliCommandInterface $command */
            $command = $container->get($className);
            $command($args, $assocArgs);
        };

        // Register with WP-CLI
        WP_CLI::add_command($commandName, $callback, [
            'shortdesc' => $attribute->description,
            'longdesc' => $attribute->longDescription,
        ]);
    }
}
