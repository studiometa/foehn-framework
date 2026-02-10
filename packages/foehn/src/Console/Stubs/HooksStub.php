<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Tempest\Discovery\SkipDiscovery;

/**
 * DummyHooks - Custom WordPress hooks.
 *
 * Group related actions and filters in a single class.
 * Use #[AsAction] for actions and #[AsFilter] for filters.
 */
#[SkipDiscovery]
final class HooksStub
{
    /**
     * Example action hook.
     *
     * Actions perform tasks and don't return values.
     */
    #[AsAction('init')]
    public function onInit(): void
    {
        // Perform initialization tasks
    }

    /**
     * Example filter hook.
     *
     * Filters modify and return data.
     */
    #[AsFilter('the_title')]
    public function filterTitle(string $title): string
    {
        // Modify the title
        return $title;
    }
}
