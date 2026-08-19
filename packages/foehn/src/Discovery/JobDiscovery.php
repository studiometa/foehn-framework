<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use ReflectionNamedType;
use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Jobs\HookNameResolver;
use Studiometa\Foehn\Jobs\JobRegistry;
use Studiometa\Foehn\Jobs\JobSerializer;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsJob] attribute
 * and registers them as Action Scheduler action handlers.
 */
final class JobDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function __construct(
        private readonly JobRegistry $jobRegistry,
    ) {}

    /**
     * Discover job handler attributes on classes.
     *
     * Validates that the class has a public `__invoke()` method
     * with exactly one typed parameter (the job DTO).
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsJob::class);

        if ($attributes === []) {
            return;
        }

        // Validate __invoke exists and is public
        if (!$class->hasMethod('__invoke') || !$class->getMethod('__invoke')->isPublic()) {
            return;
        }

        $invoke = $class->getMethod('__invoke');
        $params = $invoke->getParameters();

        // Must have exactly one typed parameter (the DTO)
        if (count($params) !== 1) {
            return;
        }

        $paramType = $params[0]->getType();

        if (!$paramType instanceof ReflectionNamedType || $paramType->isBuiltin()) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        // The DTO class comes from the handler's __invoke() signature, not the
        // attribute, so it travels beside it as its own item field.
        $this->addItem($location, [
            'attribute' => $attribute,
            'handlerClass' => $class->getName(),
            'dtoClass' => $paramType->getName(),
        ]);
    }

    /**
     * Apply discovered job handlers by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerJob($item);
        }
    }

    /**
     * Register a single job handler.
     *
     * @param array<string, mixed> $item
     */
    private function registerJob(array $item): void
    {
        /** @var AsJob $attribute */
        $attribute = $item['attribute'];
        /** @var class-string $handlerClass */
        $handlerClass = $item['handlerClass'];
        /** @var class-string $dtoClass */
        $dtoClass = $item['dtoClass'];

        $hook = HookNameResolver::forJob($dtoClass, $attribute->hook);
        $group = $attribute->group;

        // Register the DTO→handler mapping in the registry
        $this->jobRegistry->register($dtoClass, $handlerClass, $hook, $group);

        // Register the WordPress action callback
        add_action($hook, static function (array $payload) use ($handlerClass): void {
            $job = JobSerializer::deserialize($payload);
            /** @var callable $handler */
            $handler = get($handlerClass);
            $handler($job);
        });
    }
}
