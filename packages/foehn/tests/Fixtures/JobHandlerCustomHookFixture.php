<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsJob;

#[AsJob(group: 'my-plugin', hook: 'my_plugin/process_import')]
final class JobHandlerCustomHookFixture
{
    public function __invoke(JobDtoFixture $job): void
    {
        // Process import with custom hook
    }
}
