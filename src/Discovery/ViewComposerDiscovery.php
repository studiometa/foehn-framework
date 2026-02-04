<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;
use Studiometa\WPTempest\Views\ViewComposerRegistry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsViewComposer] attribute
 * and registers them with the ViewComposerRegistry.
 */
final class ViewComposerDiscovery implements Discovery
{
    use IsDiscovery;

    /**
     * Discover view composer attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsViewComposer::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements ViewComposerInterface
        if (!$class->getReflection()->implementsInterface(ViewComposerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsViewComposer]',
                $class->getName(),
                ViewComposerInterface::class,
            ));
        }

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered view composers by registering them.
     */
    public function apply(): void
    {
        /** @var ViewComposerRegistry $registry */
        $registry = get(ViewComposerRegistry::class);

        foreach ($this->discoveryItems as $item) {
            $this->registerComposer($registry, $item);
        }
    }

    /**
     * Register a single view composer.
     *
     * @param ViewComposerRegistry $registry
     * @param array{attribute: AsViewComposer, className: class-string} $item
     */
    private function registerComposer(ViewComposerRegistry $registry, array $item): void
    {
        $attribute = $item['attribute'];
        $className = $item['className'];

        /** @var ViewComposerInterface $composer */
        $composer = get($className);

        $registry->register($attribute->getTemplates(), $composer, $attribute->priority);
    }
}
