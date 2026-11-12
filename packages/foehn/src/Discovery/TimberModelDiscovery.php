<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\PostTypes\PostTypeRegistry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Timber\Post;
use Timber\Term;

/**
 * Discovers classes marked with #[AsTimberModel] attribute
 * and registers them in Timber's class map without registering
 * a post type or taxonomy.
 */
#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class TimberModelDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover timber model attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsTimberModel::class);

        if ($attribute === null) {
            return;
        }

        $isPost = $class->getReflection()->isSubclassOf(Post::class);
        $isTerm = $class->getReflection()->isSubclassOf(Term::class);

        if (!$isPost && !$isTerm) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsTimberModel]',
                sprintf(
                    '%s has #[AsTimberModel] but extends neither %s nor %s, so Timber does not map it.',
                    $class->getName(),
                    Post::class,
                    Term::class,
                ),
                sprintf(
                    'Declare %s with `extends \\%s` for a post type or `extends \\%s` for a taxonomy.',
                    $class->getName(),
                    Post::class,
                    Term::class,
                ),
            );

            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'type' => $isPost ? 'post' : 'term',
        ]);
    }

    /**
     * Apply discovered timber models by registering class maps.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerTimberModel($item);
        }
    }

    /**
     * Register a single Timber class map entry.
     *
     * @param array<string, mixed> $item
     */
    private function registerTimberModel(array $item): void
    {
        /** @var AsTimberModel $attribute */
        $attribute = $item['attribute'];
        $name = $attribute->name;
        $className = $item['className'];
        $type = $item['type'];

        $hook = $type === 'post' ? 'timber/post/classmap' : 'timber/term/classmap';

        add_filter($hook, static function (array $map) use ($name, $className): array {
            $map[$name] = $className;

            return $map;
        });

        // Register in PostTypeRegistry for QueriesPostType trait
        if ($type === 'post') {
            PostTypeRegistry::register($className, $name);
        }
    }
}
