<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ActionScheduler_Action;
use ActionScheduler_IntervalSchedule;
use ActionScheduler_Schedule;
use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Jobs\HookNameResolver;
use Studiometa\Foehn\Jobs\SiteTimezoneCronSchedule;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsCron] attribute
 * and registers them as recurring Action Scheduler actions.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
class CronDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover cron attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        try {
            $attribute = $class->getAttribute(AsCron::class);
        } catch (InvalidArgumentException $e) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsCron]',
                sprintf(
                    '%s has #[AsCron] with an invalid schedule, so the event is not scheduled. %s',
                    $class->getName(),
                    $e->getMessage(),
                ),
                'Write the expression as five fields: minute, hour, day of month, month and day of week, for example `0 0 * * *`.',
            );

            return;
        }

        if ($attribute === null) {
            return;
        }

        if (
            !$class->getReflection()->hasMethod('__invoke')
            || !$class->getReflection()->getMethod('__invoke')->isPublic()
        ) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsCron]',
                sprintf(
                    '%s has #[AsCron] but no public __invoke() method, so the event is not scheduled.',
                    $class->getName(),
                ),
                sprintf('Add `public function __invoke(): void` to %s.', $class->getName()),
            );

            return;
        }

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
        return (
            function_exists('as_schedule_recurring_action')
            && function_exists('as_get_scheduled_actions')
            && function_exists('as_has_scheduled_action')
        );
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
        $group = $attribute->group;

        // Register the callback
        add_action($hook, static function () use ($className): void {
            /** @var callable $instance */
            $instance = get($className);
            $instance();
        });

        // Before the data store is ready, the queries below answer "nothing scheduled"
        // while a save to the store still works: each request would add an action.
        if (!\ActionScheduler::is_initialized()) {
            return;
        }

        /** @var array<int, ActionScheduler_Action> $pending */
        $pending = \as_get_scheduled_actions(['hook' => $hook, 'group' => $group, 'status' => 'pending']);

        if ($pending === []) {
            // A running instance schedules its own next run when it ends.
            if (!\as_has_scheduled_action($hook, [], $group)) {
                $this->schedule($attribute, $hook, $group);
            }

            return;
        }

        foreach ($pending as $action) {
            if ($this->matches($action->get_schedule(), $attribute)) {
                continue;
            }

            // The interval or the expression changed since the action was scheduled.
            \as_unschedule_all_actions($hook, [], $group);
            $this->schedule($attribute, $hook, $group);

            return;
        }
    }

    /**
     * Whether a scheduled action already recurs the way the attribute asks.
     */
    private function matches(ActionScheduler_Schedule $schedule, AsCron $attribute): bool
    {
        if ($attribute->expression !== null) {
            return (
                $schedule instanceof SiteTimezoneCronSchedule
                && $schedule->get_recurrence() === $attribute->expression
            );
        }

        return (
            $schedule instanceof ActionScheduler_IntervalSchedule
            && (int) $schedule->get_recurrence() === $attribute->intervalSeconds
        );
    }

    /**
     * Schedule the first run.
     *
     * An expression gets a SiteTimezoneCronSchedule, saved to the store directly:
     * `as_schedule_cron_action()` would build an ActionScheduler_CronSchedule, which
     * reads the expression in UTC.
     */
    private function schedule(AsCron $attribute, string $hook, string $group): void
    {
        if ($attribute->expression === null) {
            \as_schedule_recurring_action(time(), (int) $attribute->intervalSeconds, $hook, [], $group);

            return;
        }

        $schedule = new SiteTimezoneCronSchedule(\as_get_datetime_object(), $attribute->expression);

        \ActionScheduler::store()->save_action(new ActionScheduler_Action($hook, [], $schedule, $group));
    }
}
