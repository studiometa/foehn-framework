<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Jobs\HookNameResolver;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsCron] attribute
 * and registers them as recurring Action Scheduler actions.
 */
class CronDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover cron attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsCron::class);

        if ($attributes === []) {
            return;
        }

        // Validate that the class has a public __invoke() method
        if (!$class->hasMethod('__invoke') || !$class->getMethod('__invoke')->isPublic()) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered cron jobs by registering them with Action Scheduler.
     */
    public function apply(): void
    {
        // Action Scheduler must be available
        if (!$this->isActionSchedulerAvailable()) {
            return;
        }

        foreach ($this->getItems() as $item) {
            $this->registerCron($item);
        }
    }

    /**
     * Check if Action Scheduler functions are available.
     */
    protected function isActionSchedulerAvailable(): bool
    {
        return function_exists('as_schedule_recurring_action') && function_exists('as_has_scheduled_action');
    }

    /**
     * Register a single cron job.
     *
     * @param array<string, mixed> $item
     */
    private function registerCron(array $item): void
    {
        /** @var AsCron $attribute */
        $attribute = $item['attribute'];
        /** @var string $className */
        $className = $item['className'];

        $hook = HookNameResolver::forCron($className, $attribute->hook);
        $intervalSeconds = $attribute->intervalSeconds;
        $group = $attribute->group;

        // Register the callback
        add_action($hook, static function () use ($className): void {
            /** @var callable $instance */
            $instance = get($className);
            $instance();
        });

        // Schedule if not already scheduled (idempotent)
        if (!\as_has_scheduled_action($hook, [], $group)) {
            \as_schedule_recurring_action(time(), $intervalSeconds, $hook, [], $group);
        }
    }
}
