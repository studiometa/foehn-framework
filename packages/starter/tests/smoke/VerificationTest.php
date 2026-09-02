<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn verify --profile=updates`, against a real WordPress.
 *
 * The unit suite proves the rules against function stubs. What it cannot prove is the
 * only claim this command makes: that booting a real site through a real WP-CLI process
 * raises nothing actionable — and that when something does raise a diagnostic, the
 * command notices and says where it came from.
 *
 * Two states matter, because they run different code. A cold discovery cache scans every
 * location with real reflection; a restored one hydrates attribute instances out of the
 * cache file. A deprecation in either path is a deprecation CI has to see, so the clean
 * run is asserted twice. Whichever state the machine was in is put back afterwards.
 */

/** Where the report is asked for, relative to ABSPATH — the invocation the docs show. */
const VERIFY_OUTPUT = 'build/foehn-verification.json';

/** The same file on the host. The starter installs WordPress in `web/wp`, so ABSPATH is that. */
const VERIFY_REPORT = 'web/wp/' . VERIFY_OUTPUT;

/** A mu-plugin the deprecation test writes and removes. */
const VERIFY_MU_PLUGIN = 'web/wp-content/mu-plugins/foehn-verify-smoke.php';

/** The symbol that mu-plugin reports, so the assertion can look for one exact string. */
const VERIFY_PROBE = 'foehn_verify_smoke_probe';

/**
 * Run the updates profile and hand back its exit status with the report it wrote.
 *
 * @return array{status: int, output: string, report: array<string, mixed>|null}
 */
function verifyUpdates(string $arguments = ''): array
{
    $path = Site::path(VERIFY_REPORT);

    if (is_file($path)) {
        unlink($path);
    }

    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0o777, true);
    }

    $result = Site::run(sprintf('wp foehn verify --profile=updates --output=%s %s', VERIFY_OUTPUT, $arguments));

    clearstatcache(true, $path);

    /** @var array<string, mixed>|null $report */
    $report = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    return ['status' => $result['status'], 'output' => $result['output'], 'report' => $report];
}

/**
 * How many discovery locations are cached right now, as `[cached, total]`.
 *
 * Read through grep because `Site::wp()` hands back the last line only, and the counts
 * sit in the middle of the command's output.
 *
 * @return array{0: int, 1: int}
 */
/**
 * Delete a report if there is one.
 *
 * Not `@unlink()`: a suppressed diagnostic still reaches a custom error handler, and this
 * suite runs with `failOnWarning`, so the `@` turns a missing file into a failed test
 * rather than into silence.
 */
function verifyRemoveReport(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function verifyDiscoveryState(): array
{
    $line = (string) Site::wp('wp foehn discovery:status | grep "Locations cached:"');

    return preg_match('/(\d+)\/(\d+)/', $line, $matches) === 1 ? [(int) $matches[1], (int) $matches[2]] : [0, 0];
}

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    // Restored at the end: a developer who had a warm cache should get it back, and a
    // machine that had a cold one should not be left with a warm one to explain.
    [$cached, $total] = verifyDiscoveryState();

    $GLOBALS['foehn_verify_cache_was_warm'] = $total > 0 && $cached === $total;
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    // The safety net for the deprecation test: an assertion that fails mid-test must not
    // leave a mu-plugin behind that reports a deprecation on every later request.
    $muPlugin = Site::path(VERIFY_MU_PLUGIN);

    if (is_file($muPlugin)) {
        unlink($muPlugin);
    }

    $report = Site::path(VERIFY_REPORT);

    if (is_file($report)) {
        unlink($report);
    }

    if (is_dir(dirname($report))) {
        rmdir(dirname($report));
    }

    Site::wp(
        $GLOBALS['foehn_verify_cache_was_warm'] ?? false ? 'wp foehn discovery:generate' : 'wp foehn discovery:clear',
    );
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — start it in packages/starter and try again.');
    }
});

describe('verify --profile=updates: a clean site', function () {
    it('passes with a cold discovery cache', function () {
        Site::wp('wp foehn discovery:clear');

        $run = verifyUpdates();

        expect($run['status'])->toBe(0, $run['output']);
        expect($run['report'])->not->toBeNull('no report was written to ' . VERIFY_REPORT);
        expect($run['report']['status'])->toBe('pass', $run['output']);
        expect($run['report']['checks'][0]['name'])->toBe('runtime-diagnostics');
        expect($run['report']['checks'][0]['details']['diagnostics'])->toBe([], $run['output']);
    });

    it('passes with a restored discovery cache', function () {
        Site::wp('wp foehn discovery:generate');

        [$cached, $total] = verifyDiscoveryState();

        expect($total)->toBeGreaterThan(0);
        expect($cached)->toBe($total, 'the cache did not warm, so this would be the cold path again');

        $run = verifyUpdates();

        expect($run['status'])->toBe(0, $run['output']);
        expect($run['report'])->not->toBeNull('no report was written to ' . VERIFY_REPORT);
        expect($run['report']['status'])->toBe('pass', $run['output']);
        expect($run['report']['checks'][0]['details']['diagnostics'])->toBe([], $run['output']);
    });

    it('writes a report that names no machine', function () {
        $run = verifyUpdates();

        // The one determinism claim a stubbed test cannot make: this report came off a
        // real install, with real paths available to leak into it.
        $json = (string) file_get_contents(Site::path(VERIFY_REPORT));

        expect($json)->not->toContain('/var/www/html');
        expect($json)->not->toMatch('#"/#');
        expect($run['status'])->toBe(0, $run['output']);
    });

    it('renders the report itself with --format=json', function () {
        $run = verifyUpdates('--format=json');

        expect($run['status'])->toBe(0, $run['output']);
        expect($run['output'])->toContain('"profile": "updates"');
    });
});

describe('verify --profile=updates: a site with a deprecation', function () {
    beforeEach(function () {
        // A mu-plugin rather than a theme change: it loads before the theme boots Føhn,
        // and reports on `init`, which the command reaches — so this exercises the same
        // path a real plugin deprecation takes after a WordPress update.
        //
        // No opcache wait, unlike the page-cache config files: WP-CLI runs under the CLI
        // SAPI, which does not cache the file between the write and the next command.
        file_put_contents(
            Site::path(VERIFY_MU_PLUGIN),
            sprintf(
                "<?php\n\n"
                . "// Written by the smoke suite, removed when it finishes.\n"
                . "add_action('init', static function (): void {\n"
                . "    _doing_it_wrong('%s', 'Injected by the smoke suite.', '1.0.0');\n"
                . "});\n",
                VERIFY_PROBE,
            ),
        );
    });

    afterEach(function () {
        $muPlugin = Site::path(VERIFY_MU_PLUGIN);

        if (is_file($muPlugin)) {
            unlink($muPlugin);
        }
    });

    it('exits 1, writes the report anyway, and names what raised it', function () {
        $run = verifyUpdates();

        expect($run['status'])->toBe(1, $run['output']);
        expect($run['report'])->not->toBeNull('the report must be written on a failure too');
        expect($run['report']['status'])->toBe('fail');

        $diagnostics = $run['report']['checks'][0]['details']['diagnostics'];

        expect(array_column($diagnostics, 'symbol'))->toContain(VERIFY_PROBE);
        expect(array_column($diagnostics, 'type'))->toContain('doing_it_wrong');

        // The file the hook itself does not carry, derived from the backtrace.
        $probe = array_values(array_filter(
            $diagnostics,
            static fn(array $item): bool => $item['symbol'] === VERIFY_PROBE,
        ))[0];

        expect($probe['file'])->toBe('wp-content/mu-plugins/foehn-verify-smoke.php');
        expect($probe['message'])->toBe('Injected by the smoke suite.');
    });
});

describe('verify --profile=production: a site that is not production', function () {
    it('refuses to pass on a local site, rather than adapting to it', function () {
        // The starter's ddev site runs WP_ENVIRONMENT_TYPE=local, so this is the negative
        // case and it is the one worth having in a real WordPress: the profile does not
        // relax its rules for the environment it finds. A gate that did would wave
        // through a production machine whose WP_ENVIRONMENT_TYPE was simply wrong.
        //
        // Exit 1, not 2: the gate ran and the site failed it. A deploy script has to be
        // able to tell that from "the gate could not run".
        $run = Site::run('wp foehn verify --profile=production');

        expect($run['status'])->toBe(1, $run['output']);
        expect($run['output'])->toContain('environment');
        expect($run['output'])->toContain('not production');
    });

    it('runs every check it promises, on a real WordPress', function () {
        // The profile exists only because all eight do. Asserting the names here is what
        // would catch a check quietly dropped from the assembler — the report would still
        // say `pass`, with less behind it than the name implies.
        $path = Site::path(VERIFY_REPORT);
        verifyRemoveReport($path);

        Site::run('wp foehn verify --profile=production --output=' . VERIFY_OUTPUT);

        expect($path)->toBeFile();
        $report = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect($report['profile'])->toBe('production');
        expect(array_column($report['checks'], 'name'))->toBe([
            'cron-backlog',
            'cron-heartbeat',
            'debug',
            'environment',
            'indexing',
            'page-cache-storage',
            'real-cron',
            'salts',
        ]);

        verifyRemoveReport($path);
    });

    it('writes a report carrying no key, no path and no timestamp', function () {
        // The artifact CI keeps. A salt in it would be a secret in a build log, and a
        // timestamp would make two runs of an unchanged site undiffable.
        $path = Site::path(VERIFY_REPORT);
        verifyRemoveReport($path);

        Site::run('wp foehn verify --profile=production --output=' . VERIFY_OUTPUT);
        $json = (string) file_get_contents($path);

        foreach (['AUTH_SALT' => null, 'NONCE_SALT' => null] as $name => $_) {
            $value = Site::wp('wp eval ' . escapeshellarg("echo defined('{$name}') ? {$name} : '';"));

            if (is_string($value) && strlen($value) > 12) {
                expect($json)->not->toContain(substr($value, 0, 12));
            }
        }

        expect($json)->not->toContain('/var/www/html');
        expect($json)->not->toContain(Site::path(''));
        expect($json)->not->toMatch('/\b2\d{9}\b/');

        verifyRemoveReport($path);
    });
});

describe('verify: the profile is a required, closed choice', function () {
    it('refuses to run without a profile', function () {
        $run = Site::run('wp foehn verify');

        expect($run['status'])->toBe(2, $run['output']);
    });

    it('refuses updates without an output path', function () {
        $run = Site::run('wp foehn verify --profile=updates');

        expect($run['status'])->toBe(2, $run['output']);
    });
});
