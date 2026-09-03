<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\Env;

/**
 * Run a snippet in a PHP process with the autoloader but **without** the WordPress
 * stubs, and hand back what it printed.
 *
 * `wp_get_environment_type()` is the first step of the resolution and the stubs define
 * it, so inside this suite it always wins and the three fallbacks below it are
 * unreachable. Those fallbacks are the ones the page-cache drop-in actually uses — it
 * runs from `wp-settings.php`, before `wp-includes/load.php` has defined the function —
 * so a suite that could not reach them would be leaving the one caller that needs them
 * untested. Hence a subprocess: the same trick the drop-in's own tests use.
 */
function envInProcessWithoutWordPress(string $snippet): string
{
    // Both layouts the suite runs in, as `tests/bootstrap.php` resolves them: the
    // monorepo's vendor directory at the repository root, or the package's own.
    $monorepo = dirname(__DIR__, 5) . '/vendor/autoload.php';
    $standalone = dirname(__DIR__, 3) . '/vendor/autoload.php';
    $autoload = file_exists($monorepo) ? $monorepo : $standalone;
    $script = sprintf('require %s; %s', var_export($autoload, true), $snippet);

    $output = [];
    $status = 0;
    exec('php -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

    expect($status)->toBe(0, implode("\n", $output));

    return $output[0] ?? '';
}

beforeEach(function (): void {
    wp_stub_reset();
    // Unset rather than reset: `wp_stub_reset()` leaves this global alone, so a value
    // one case sets is still what `wp_get_environment_type()` answers in the next —
    // and the case that asserts the default would then be passing or failing on
    // whichever test ran before it.
    unset($GLOBALS['wp_stub_environment_type']);
    putenv('WP_ENVIRONMENT_TYPE');
});

afterEach(function (): void {
    unset($GLOBALS['wp_stub_environment_type']);
    putenv('WP_ENVIRONMENT_TYPE');
});

describe('Env: the environment WordPress reports', function (): void {
    it('takes what wp_get_environment_type() answers', function (): void {
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        expect(Env::get())->toBe('staging');
    });

    it('prefers WordPress over the environment variable', function (): void {
        // Not a preference: `wp_get_environment_type()` applies WordPress's own
        // allowlist and its `WP_ENVIRONMENT_TYPE` filter, so a plugin or a config file
        // can correct the raw value. Reading the variable first would silently ignore
        // both.
        $GLOBALS['wp_stub_environment_type'] = 'staging';
        putenv('WP_ENVIRONMENT_TYPE=production');

        expect(Env::get())->toBe('staging');
    });

    it('reads the constant when WordPress is not loaded yet', function (): void {
        expect(envInProcessWithoutWordPress(
            "define('WP_ENVIRONMENT_TYPE', 'staging'); echo Studiometa\\Foehn\\Helpers\\Env::get();",
        ))
            ->toBe('staging');
    });

    it('reads the environment variable when there is no constant either', function (): void {
        expect(envInProcessWithoutWordPress(
            "putenv('WP_ENVIRONMENT_TYPE=development'); echo Studiometa\\Foehn\\Helpers\\Env::get();",
        ))
            ->toBe('development');
    });

    it('prefers the constant over the environment variable', function (): void {
        expect(envInProcessWithoutWordPress(
            "define('WP_ENVIRONMENT_TYPE', 'staging');"
            . "putenv('WP_ENVIRONMENT_TYPE=development');"
            . 'echo Studiometa\\Foehn\\Helpers\\Env::get();',
        ))->toBe('staging');
    });

    it('treats an empty constant as absent rather than as an environment', function (): void {
        expect(envInProcessWithoutWordPress(
            "define('WP_ENVIRONMENT_TYPE', '');"
            . "putenv('WP_ENVIRONMENT_TYPE=staging');"
            . 'echo Studiometa\\Foehn\\Helpers\\Env::get();',
        ))->toBe('staging');
    });

    it('falls back to production, which is what WordPress does', function (): void {
        expect(envInProcessWithoutWordPress('echo Studiometa\\Foehn\\Helpers\\Env::get();'))->toBe('production');
    });

    it('reads no .env file of its own', function (): void {
        // Production injects environment variables without ever writing the file, so a
        // framework that needed one would be reading nothing exactly where being right
        // matters. This pins that: a .env sitting next to the process is not consulted.
        $directory = sys_get_temp_dir() . '/foehn-tests/env-' . uniqid('', true);
        mkdir($directory, 0o755, true);
        file_put_contents($directory . '/.env', "WP_ENVIRONMENT_TYPE=staging\n");

        try {
            expect(envInProcessWithoutWordPress(sprintf('chdir(%s); echo Studiometa\\Foehn\\Helpers\\Env::get();', var_export(
                $directory,
                true,
            ))))
                ->toBe('production');
        } finally {
            removeTestDirectory($directory);
        }
    });
});

describe('Env: the questions callers actually ask', function (): void {
    it('answers is() against the resolved environment', function (): void {
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        expect(Env::is('staging'))->toBeTrue();
        expect(Env::is('production'))->toBeFalse();
    });

    it('names each of the four environments WordPress defines', function (): void {
        $expected = [
            'production' => ['isProduction'],
            'staging' => ['isStaging'],
            'development' => ['isDevelopment'],
            'local' => ['isLocal'],
        ];

        foreach ($expected as $environment => $trueFor) {
            $GLOBALS['wp_stub_environment_type'] = $environment;

            foreach (['isProduction', 'isStaging', 'isDevelopment', 'isLocal'] as $method) {
                expect(Env::{$method}())
                    ->toBe(
                        in_array($method, $trueFor, true),
                        sprintf('Env::%s() in the %s environment', $method, $environment),
                    );
            }
        }
    });

    it('does not read development as local', function (): void {
        // It used to, back when the environment came from APP_ENV and `development` was
        // what a laptop was called. WordPress defines the two as separate types — a
        // laptop and a shared server somebody develops against — and conflating them
        // makes the one question this method exists to answer unanswerable.
        $GLOBALS['wp_stub_environment_type'] = 'development';

        expect(Env::isLocal())->toBeFalse();
    });

    it('reports production for an environment nothing configured', function (): void {
        expect(Env::isProduction())->toBeTrue();
    });
});

describe('Env: debug mode', function (): void {
    it('reads WP_DEBUG', function (): void {
        // Defined by the suite's own bootstrap or not at all, so the assertion is
        // against the constant rather than against a value this test could set.
        expect(Env::isDebug())->toBe(defined('WP_DEBUG') && (bool) constant('WP_DEBUG'));
    });

    it('reports no debug mode when WP_DEBUG is not defined', function (): void {
        expect(envInProcessWithoutWordPress('echo Studiometa\\Foehn\\Helpers\\Env::isDebug() ? "yes" : "no";'))
            ->toBe('no');
    });

    it('reports debug mode when WP_DEBUG is on', function (): void {
        expect(envInProcessWithoutWordPress(
            "define('WP_DEBUG', true); echo Studiometa\\Foehn\\Helpers\\Env::isDebug() ? 'yes' : 'no';",
        ))
            ->toBe('yes');
    });
});
