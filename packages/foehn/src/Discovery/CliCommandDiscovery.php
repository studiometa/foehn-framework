<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\CliNamespace;
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
 * Each source of commands has its own WP-CLI namespace:
 *
 * - the project's commands go under `wp app`, or the name FoehnConfig::$cliNamespace gives;
 * - the framework's go under `wp foehn`;
 * - each other package's go under the name in `extra.foehn.cli-namespace` of its
 *   composer.json, or else its package name without the vendor: `wp foehn-acf`.
 *
 * A command of one source can then never replace a command of another that a later
 * release adds, and `wp app` lists only the site's.
 */
#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class CliCommandDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly Container $container,
        private readonly DiscoveryProblems $problems,
        private readonly FoehnConfig $config,
        private readonly InstalledPackages $packages,
    ) {}

    /**
     * The namespace of each location scanned so far, by location path.
     *
     * @var array<string, array{namespace: string|null, package: string|null}>
     */
    private array $namespaces = [];

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

        $this->namespaces[$location->path] ??= $this->namespaceOf($location);
        $source = $this->namespaces[$location->path];

        if ($source['package'] !== null && $source['namespace'] === null) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsCliCommand]',
                sprintf(
                    'The package %s has no valid WP-CLI namespace, so %s is not registered. A namespace is %s.',
                    $source['package'],
                    $class->getName(),
                    CliNamespace::RULE,
                ),
                sprintf(
                    'Set "extra": {"foehn": {"cli-namespace": "<name>"}} in the composer.json of %s.',
                    $source['package'],
                ),
            );

            return;
        }

        $this->addItem($location, [
            'attribute' => $class->getAttribute(AsCliCommand::class),
            'className' => $class->getName(),
            'namespace' => $source['namespace'],
        ]);
    }

    /**
     * The namespace of the commands in a location: null for the project's.
     *
     * The project's is left out of the item and read from FoehnConfig when the
     * command is registered, so a change of FoehnConfig::$cliNamespace applies
     * without a discovery cache rebuild. A package with no valid namespace gives
     * its name and a null namespace.
     *
     * @return array{namespace: string|null, package: string|null}
     */
    private function namespaceOf(DiscoveryLocation $location): array
    {
        $package = $this->packages->at($location);

        if ($package === null) {
            return ['namespace' => null, 'package' => null];
        }

        if ($package['name'] === CliNamespace::FRAMEWORK_PACKAGE) {
            return ['namespace' => CliNamespace::FRAMEWORK, 'package' => $package['name']];
        }

        $foehn = $package['extra']['foehn'] ?? [];
        $namespace = is_array($foehn) && array_key_exists('cli-namespace', $foehn)
            ? $foehn['cli-namespace']
            : substr($package['name'], (int) strrpos($package['name'], '/') + 1);

        return [
            'namespace' => is_string($namespace) && CliNamespace::isValid($namespace) ? $namespace : null,
            'package' => $package['name'],
        ];
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

        $byCommand = [];

        foreach ($this->getItems() as $item) {
            $byCommand[$this->commandName($item)][] = $item;
        }

        foreach ($byCommand as $commandName => $items) {
            // Two classes for one command: WP-CLI would keep the one registered last,
            // and which one that is depends on scan order. Neither is registered, so
            // the clash shows on the first run instead of after a cache rebuild.
            if (count($items) > 1) {
                WP_CLI::warning(sprintf(
                    '`wp %s` is declared by %s. None of them is registered: give each a command name of its own.',
                    $commandName,
                    implode(' and ', array_column($items, 'className')),
                ));

                continue;
            }

            /** @var AsCliCommand $attribute */
            $attribute = $items[0]['attribute'];

            $this->registerCommand($commandName, $attribute, $items[0]['className']);
        }
    }

    /**
     * The WP-CLI command an item registers, without the leading `wp`.
     *
     * @param array<string, mixed> $item
     */
    public function commandName(array $item): string
    {
        /** @var AsCliCommand $attribute */
        $attribute = $item['attribute'];

        /** @var string|null $namespace */
        $namespace = $item['namespace'];

        return ($namespace ?? $this->config->cliNamespace) . ' ' . $attribute->name;
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
