<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Container\GenericContainer;

describe('DiscoveryRunner', function () {
    it('returns all discovery classes', function () {
        $classes = DiscoveryRunner::getAllDiscoveryClasses();

        expect($classes)->toContain(\Studiometa\Foehn\Discovery\HookDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\PostTypeDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\TaxonomyDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\MenuDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\ShortcodeDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\CliCommandDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\AcfBlockDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\BlockDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\BlockPatternDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\ViewComposerDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\TemplateControllerDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\RestRouteDiscovery::class);
        expect($classes)->toContain(\Studiometa\Foehn\Discovery\TimberModelDiscovery::class);
    });

    it('returns discovery phases', function () {
        $phases = DiscoveryRunner::getDiscoveryPhases();

        expect($phases)->toHaveKeys(['early', 'main', 'late']);

        // Early phase
        expect($phases['early'])->toContain(\Studiometa\Foehn\Discovery\HookDiscovery::class);
        expect($phases['early'])->toContain(\Studiometa\Foehn\Discovery\ShortcodeDiscovery::class);
        expect($phases['early'])->toContain(\Studiometa\Foehn\Discovery\CliCommandDiscovery::class);
        expect($phases['early'])->toContain(\Studiometa\Foehn\Discovery\TimberModelDiscovery::class);

        // Main phase
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\PostTypeDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\TaxonomyDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\MenuDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\AcfBlockDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\BlockDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\Foehn\Discovery\BlockPatternDiscovery::class);

        // Late phase
        expect($phases['late'])->toContain(\Studiometa\Foehn\Discovery\ViewComposerDiscovery::class);
        expect($phases['late'])->toContain(\Studiometa\Foehn\Discovery\TemplateControllerDiscovery::class);
        expect($phases['late'])->toContain(\Studiometa\Foehn\Discovery\RestRouteDiscovery::class);
    });

    it('has correct number of discoveries in each phase', function () {
        $phases = DiscoveryRunner::getDiscoveryPhases();

        expect($phases['early'])->toHaveCount(4);
        expect($phases['main'])->toHaveCount(7);
        expect($phases['late'])->toHaveCount(3);
    });

    it('all discovery classes total matches phase sum', function () {
        $phases = DiscoveryRunner::getDiscoveryPhases();
        $all = DiscoveryRunner::getAllDiscoveryClasses();

        $phaseTotal = count($phases['early']) + count($phases['main']) + count($phases['late']);

        expect(count($all))->toBe($phaseTotal);
    });

    it('all discovery classes implement WpDiscovery', function () {
        $classes = DiscoveryRunner::getAllDiscoveryClasses();

        foreach ($classes as $class) {
            expect(is_subclass_of($class, \Studiometa\Foehn\Discovery\WpDiscovery::class))
                ->toBeTrue("Expected {$class} to implement WpDiscovery");
        }
    });
});

describe('DiscoveryRunner debug logging', function () {
    it('logs reflection failures when debug is enabled', function () {
        $container = new GenericContainer();
        $config = new FoehnConfig(debug: true);

        $runner = new DiscoveryRunner(container: $container, cache: null, appPath: null, config: $config);

        // Use reflection to access the private logDiscoveryFailure method
        $method = new ReflectionMethod($runner, 'logDiscoveryFailure');

        $warningTriggered = false;
        $warningMessage = '';

        set_error_handler(function ($errno, $errstr) use (&$warningTriggered, &$warningMessage) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
                $warningMessage = $errstr;
            }

            return true;
        });

        try {
            $exception = new ReflectionException('Class not found');
            $method->invoke($runner, 'App\\NonExistentClass', $exception);
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeTrue();
        expect($warningMessage)->toContain('[Foehn] Discovery failed for class "App\\NonExistentClass"');
        expect($warningMessage)->toContain('Class not found');
    });

    it('does not log reflection failures when debug is disabled', function () {
        $container = new GenericContainer();
        $config = new FoehnConfig(debug: false);

        $runner = new DiscoveryRunner(container: $container, cache: null, appPath: null, config: $config);

        // Use reflection to access the private logDiscoveryFailure method
        $method = new ReflectionMethod($runner, 'logDiscoveryFailure');

        $warningTriggered = false;

        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }

            return true;
        });

        try {
            $exception = new ReflectionException('Class not found');
            $method->invoke($runner, 'App\\NonExistentClass', $exception);
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeFalse();
    });

    it('does not log reflection failures when config is null', function () {
        $container = new GenericContainer();

        $runner = new DiscoveryRunner(container: $container, cache: null, appPath: null, config: null);

        // Use reflection to access the private logDiscoveryFailure method
        $method = new ReflectionMethod($runner, 'logDiscoveryFailure');

        $warningTriggered = false;

        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }

            return true;
        });

        try {
            $exception = new ReflectionException('Class not found');
            $method->invoke($runner, 'App\\NonExistentClass', $exception);
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeFalse();
    });
});
