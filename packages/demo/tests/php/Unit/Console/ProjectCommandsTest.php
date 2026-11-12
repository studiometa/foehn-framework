<?php

declare(strict_types=1);

use Demo\Console\ProjectsReorderCommand;
use Demo\Console\ProjectsStatsCommand;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;

function demoCommand(string $class): AsCliCommand
{
    return new ReflectionClass($class)->getAttributes(AsCliCommand::class)[0]->newInstance();
}

describe('Project commands', function () {
    it('lists the series with a command that only reads', function () {
        $command = demoCommand(ProjectsStatsCommand::class);

        expect(is_subclass_of(ProjectsStatsCommand::class, CliCommandInterface::class))->toBeTrue();
        expect($command->name)->toBe('projects:stats');
        expect($command->writes)->toBeFalse();
    });

    it('reorders the series with a command that writes', function () {
        $command = demoCommand(ProjectsReorderCommand::class);

        expect($command->name)->toBe('projects:reorder');
        expect($command->writes)->toBeTrue();
        // The guard names these two as the way out in production, and WP-CLI refuses
        // an option the long description does not declare.
        expect($command->longDescription)->toContain('[--dry-run]')->toContain('[--force]');
    });
});
