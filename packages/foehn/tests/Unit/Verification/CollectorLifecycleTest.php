<?php

declare(strict_types=1);

use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Verification\Updates\DiagnosticsCollector;
use Studiometa\Foehn\Verification\VerifyCommand;

/**
 * When the kernel starts the collector, and when it leaves it alone.
 *
 * `WP_CLI` is a constant, so the case where it is defined runs in a subprocess: defining
 * it here would leave every later test in the run believing it was under WP-CLI.
 */
describe('Kernel: the diagnostics collector', function () {
    afterEach(function () {
        Kernel::reset();
        wp_stub_reset();
    });

    it('registers one collector for the whole process', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(DiagnosticsCollector::class))->toBe(Kernel::get(DiagnosticsCollector::class));
    });

    it('builds the command WP-CLI resolves, and hands it that collector', function () {
        // The command is resolved from the container by CliCommandDiscovery, so a
        // dependency it cannot autowire is a failure nothing but a real `wp foehn verify`
        // would show.
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(VerifyCommand::class))->toBeInstanceOf(VerifyCommand::class);
    });

    it('does not start it outside WP-CLI', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        // An HTTP request would pay for an error handler and three hooks that no command
        // will ever read.
        expect(Kernel::get(DiagnosticsCollector::class)->isStarted())->toBeFalse();

        $hooks = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook');

        expect($hooks)->not->toContain('deprecated_function_run');
    });

    it('starts it under WP-CLI, before Timber and before the lifecycle hooks', function () {
        $script = <<<'PHP'
            require %s;
            define('WP_CLI', true);
            Studiometa\Foehn\Kernel::boot(%s, []);
            $collector = Studiometa\Foehn\Kernel::get(
                Studiometa\Foehn\Verification\Updates\DiagnosticsCollector::class,
            );
            $hooks = [];
            foreach ($GLOBALS['wp_stub_calls'] as $call) {
                if ($call['function'] === 'add_action') {
                    $hooks[] = $call['args']['hook'];
                }
            }
            echo 'started:', $collector->isStarted() ? 'yes' : 'no', "\n";
            echo 'hooks:', implode(',', $hooks), "\n";
            PHP;

        $output = [];
        $status = 0;
        exec(
            'php -r '
            . escapeshellarg(sprintf(
                $script,
                var_export(dirname(__DIR__, 2) . '/bootstrap.php', true),
                var_export(dirname(__DIR__, 3) . '/src', true),
            ))
            . ' 2>&1',
            $output,
            $status,
        );

        $printed = implode("\n", $output);

        expect($status)->toBe(0, $printed);
        expect($printed)->toContain('started:yes');

        $hooks = explode(',', str_replace('hooks:', '', $output[1] ?? ''));

        expect($hooks)->toContain('deprecated_function_run');
        expect($hooks)->toContain('deprecated_hook_run');
        expect($hooks)->toContain('doing_it_wrong_run');

        // Registered before the lifecycle hooks, which is the whole point of the
        // placement: discovery and everything the command reaches afterwards can raise a
        // deprecation, and an update review wants to see it.
        expect(array_search('deprecated_function_run', $hooks, true))
            ->toBeLessThan((int) array_search('after_setup_theme', $hooks, true));
    });
});
