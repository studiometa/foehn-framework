<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TimberViewEngine;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsContextProvider] attribute
 * and registers them with the ContextProviderRegistry.
 */
#[AsDiscovery(phase: DiscoveryPhase::Late)]
final class ContextProviderDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover context provider attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsContextProvider::class);

        if ($attribute === null) {
            return;
        }

        if (!$class->implements(ContextProviderInterface::class)) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsContextProvider]',
                sprintf(
                    '%s has #[AsContextProvider] but does not implement %s, so it provides no context.',
                    $class->getName(),
                    ContextProviderInterface::class,
                ),
                sprintf('Declare %s with `implements \\%s`.', $class->getName(), ContextProviderInterface::class),
            );

            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered context providers by registering them.
     */
    public function apply(): void
    {
        /** @var ContextProviderRegistry $registry */
        $registry = get(ContextProviderRegistry::class);

        foreach ($this->getItems() as $item) {
            /** @var AsContextProvider $attribute */
            $attribute = $item['attribute'];
            /** @var ContextProviderInterface $provider */
            $provider = get($item['className']);

            $registry->register($attribute->getTemplates(), $provider, $attribute->priority);
        }

        if ($registry->count() === 0) {
            return;
        }

        // A view rendered with Timber directly, not through the view engine, gets its
        // providers too: see TimberViewEngine::provideTimberData(). The engine is
        // resolved when Timber renders, not at boot.
        $provide = static function (mixed $data, mixed $file): mixed {
            $engine = get(ViewEngineInterface::class);

            return $engine instanceof TimberViewEngine && is_array($data) && is_string($file)
                ? $engine->provideTimberData($data, $file)
                : $data;
        };

        add_filter('timber/compile/data', $provide, 10, 2);
        add_filter('timber/render/data', $provide, 10, 2);
    }
}
