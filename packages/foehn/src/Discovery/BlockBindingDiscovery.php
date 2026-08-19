<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_Block;

/**
 * Discovers classes marked with #[AsBlockBinding] and registers them as block
 * bindings sources.
 *
 * `register_block_bindings_source()` must be called on `init`, so this is a Main
 * phase discovery.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class BlockBindingDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Discover block binding sources.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsBlockBinding::class);

        if ($attribute === null) {
            return;
        }

        if (!$class->implements(BlockBindingInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsBlockBinding].',
                $class->getName(),
                BlockBindingInterface::class,
            ));
        }

        // WordPress refuses a name without a namespace, and says so through
        // _doing_it_wrong() — which is to say only under WP_DEBUG.
        if (!str_contains($attribute->name, '/')) {
            throw new InvalidArgumentException(sprintf(
                '#[AsBlockBinding] on %s names the source \'%s\'. It must be namespaced, as in \'theme/%s\'.',
                $class->getName(),
                $attribute->name,
                $attribute->name,
            ));
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered block binding sources.
     */
    public function apply(): void
    {
        // Block bindings arrived in WordPress 6.5. A site older than that gets
        // no sources rather than a fatal error.
        if (!function_exists('register_block_bindings_source')) {
            return;
        }

        foreach ($this->getItems() as $item) {
            $this->registerSource($item);
        }
    }

    /**
     * Register one source with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerSource(array $item): void
    {
        /** @var AsBlockBinding $attribute */
        $attribute = $item['attribute'];
        /** @var class-string<BlockBindingInterface> $className */
        $className = $item['className'];

        $container = $this->container;

        register_block_bindings_source($attribute->name, [
            'label' => $attribute->label,
            'uses_context' => $attribute->usesContext,
            // Built here and never stored: a callable cannot survive the
            // discovery cache. The instance is resolved when a bound block is
            // rendered, not when the source is registered, so a source nothing
            // binds to costs nothing.
            'get_value_callback' => static fn(
                array $sourceArgs,
                WP_Block $block,
                string $attributeName,
            ): ?string => $container->get($className)->value($sourceArgs, $block, $attributeName),
        ]);
    }
}
