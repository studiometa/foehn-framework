<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionNamedType;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Jobs\HookNameResolver;
use Studiometa\Foehn\Jobs\JobRegistry;
use Studiometa\Foehn\Jobs\JobSerializer;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsJob] attribute
 * and registers them as Action Scheduler action handlers.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class JobDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly JobRegistry $jobRegistry,
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover job handler attributes on classes.
     *
     * Validates that the class has a public `__invoke()` method
     * with exactly one typed parameter (the job DTO).
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsJob::class);

        if ($attribute === null) {
            return;
        }

        $fix = sprintf(
            'Give %s a public __invoke() method whose one parameter is typed with the job class.',
            $class->getName(),
        );

        if (
            !$class->getReflection()->hasMethod('__invoke')
            || !$class->getReflection()->getMethod('__invoke')->isPublic()
        ) {
            $this->reject($location, $class, 'has no public __invoke() method', $fix);

            return;
        }

        $params = $class->getReflection()->getMethod('__invoke')->getParameters();

        if (count($params) !== 1) {
            $this->reject(
                $location,
                $class,
                sprintf('has an __invoke() method with %d parameters instead of one', count($params)),
                $fix,
            );

            return;
        }

        $paramType = $params[0]->getType();

        if (!$paramType instanceof ReflectionNamedType || $paramType->isBuiltin()) {
            $this->reject($location, $class, 'has an __invoke() parameter that is not typed with a class', $fix);

            return;
        }

        // The DTO class comes from the handler's __invoke() signature, not the
        // attribute, so it travels beside it as its own item field.
        $this->addItem($location, [
            'attribute' => $attribute,
            'handlerClass' => $class->getName(),
            'dtoClass' => $paramType->getName(),
        ]);
    }

    /**
     * Report a handler whose __invoke() cannot receive a job.
     */
    private function reject(DiscoveryLocation $location, ClassReflector $class, string $what, string $fix): void
    {
        $this->problems->report(
            $location,
            $class->getName(),
            '#[AsJob]',
            sprintf('%s has #[AsJob] but %s, so it handles no job.', $class->getName(), $what),
            $fix,
        );
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
