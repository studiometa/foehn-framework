<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The production profile against a real WordPress, one case per way a deploy is unsafe.
 *
 * The unit suite proves each check's judgement. What it cannot prove is that the profile
 * reads the site correctly: `WP_ENVIRONMENT_TYPE` arrives through a generated
 * `wp-config.php`, `DISABLE_WP_CRON` is derived from an environment variable, the salts
 * are constants defined from `.env`, and `blog_public` and the heartbeat are database
 * rows. Every one of those is a way for a check that tests green to be reading the wrong
 * thing.
 *
 * **Production is simulated per invocation, and nothing persistent is edited.** The
 * generated `wp-config.php` loads `.env` through phpdotenv's *immutable* loader, so a
 * variable already present in the environment wins over the file. `env FOO=bar wp …`
 * inside the container is therefore a complete production simulation that lasts exactly
 * one command — no file rewritten, no container rebuilt, and no chance of a half-restored
 * `.env` making the next test lie.
 *
 * The two database values that cannot be simulated that way — `blog_public` and the cron
 * heartbeat — are saved and put back.
 */

/** What a production deployment's environment looks like, before a case breaks one thing. */
const PRODUCTION_ENV = [
    'WP_ENVIRONMENT_TYPE' => 'production',
    'WP_DEBUG' => 'false',
    'FOEHN_CRON_ENABLED' => 'true',
];

/**
 * Run the production profile with the environment a real deploy would have, minus
 * whatever this case is breaking.
 *
 * @param array<string, string> $overrides
 * @return array{status: int, failed: list<string>, output: string, report: string}
 */
function productionVerify(array $overrides = []): array
{
    $assignments = [];

    foreach ([...PRODUCTION_ENV, ...$overrides] as $name => $value) {
        $assignments[] = $name . '=' . escapeshellarg($value);
    }

    $run = Site::run(sprintf('env %s wp foehn verify --profile=production --format=json', implode(' ', $assignments)));

    return [
        'status' => $run['status'],
        'failed' => productionFailedChecks($run['output']),
        'output' => $run['output'],
        'report' => productionReport($run['output']),
    ];
}

/**
 * The report, cut out of the surrounding noise.
 *
 * Separate from the whole output on purpose. The output also carries the command line
 * this test built, so asserting "no secret appears in the output" would be asserting
 * something about the test's own arguments — which is how a redaction assertion comes to
 * fail while the redaction works.
 */
function productionReport(string $output): string
{
    $start = strpos($output, '{');
    $end = strrpos($output, '}');

    return $start === false || $end === false ? '' : substr($output, $start, $end - $start + 1);
}

/**
 * The names of the checks that failed, sorted.
 *
 * Parsed out of the surrounding noise rather than taken whole: WP-CLI in this image
 * prints deprecations from its own Phar before the report, and with `WP_DEBUG=true` the
 * site adds more of its own.
 *
 * @return list<string>
 */
function productionFailedChecks(string $output): array
{
    $report = json_decode(productionReport($output), true);

    if (!is_array($report) || !isset($report['checks'])) {
        return ['NO-REPORT'];
    }

    $failed = [];

    foreach ($report['checks'] as $check) {
        if (($check['status'] ?? null) === 'fail') {
            $failed[] = (string) $check['name'];
        }
    }

    sort($failed);

    return $failed;
}

/**
 * Put a heartbeat in the database as the Docker cron runner would.
 */
function productionHeartbeat(string $value): void
{
    Site::run(sprintf('wp option update %s %s --autoload=no', 'foehn_cron_last_run', escapeshellarg($value)));
}

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    $GLOBALS['foehn_production_blog_public'] = Site::wp('wp option get blog_public');
    Site::run('wp option update blog_public 1');
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    // The heartbeat is deleted rather than restored: nothing on a local site writes it,
    // so its real value is "absent" and leaving one behind would make the dashboard of
    // a developer's own site claim a cron runner it does not have.
    Site::run('wp option delete foehn_cron_last_run');

    $public = $GLOBALS['foehn_production_blog_public'] ?? null;

    if (is_string($public) && $public !== '') {
        Site::run('wp option update blog_public ' . escapeshellarg($public));
    }
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — start it in packages/starter and try again.');
    }

    // Every case starts from a configuration that passes, so what it asserts is the one
    // thing it broke rather than whatever else the site happened to be doing.
    productionHeartbeat((string) time());
});

describe('verify --profile=production: a safe production configuration', function () {
    it('passes every check, and exits 0', function () {
        // The acceptance criterion the whole profile exists for. If this ever fails, read
        // the failed check names before anything else: they say which of the eight is
        // reading the site wrongly.
        $run = productionVerify();

        expect($run['failed'])->toBe([], 'checks failed on a safe configuration — ' . $run['output']);
        expect($run['status'])->toBe(0, $run['output']);
    });
});

describe('verify --profile=production: one unsafe thing at a time', function () {
    it('fails the environment check, and the indexing check with it', function () {
        // Two symptoms of one cause, and both are worth reporting: outside production
        // Føhn's own indexing guard activates, so a site mislabelled as staging is both
        // in the wrong environment and actively telling search engines to go away.
        $run = productionVerify(['WP_ENVIRONMENT_TYPE' => 'staging']);

        expect($run['failed'])->toBe(['environment', 'indexing']);
        expect($run['status'])->toBe(1);
    });

    it('fails only the debug check when WP_DEBUG is on', function () {
        // And only that one: the generated wp-config derives WP_DEBUG_DISPLAY from the
        // environment, so it stays off in production even with debugging on.
        $run = productionVerify(['WP_DEBUG' => 'true']);

        expect($run['failed'])->toBe(['debug']);
        expect($run['status'])->toBe(1);
    });

    it('fails only the real-cron check when nothing runs the scheduler', function () {
        // FOEHN_CRON_ENABLED is what the generated wp-config reads to decide
        // DISABLE_WP_CRON, so turning it off leaves the site on visitor-driven cron —
        // which a page cache removes.
        expect(productionVerify(['FOEHN_CRON_ENABLED' => 'false'])['failed'])->toBe(['real-cron']);
    });

    it('fails only the indexing check when WordPress is discouraging search engines', function () {
        // The row that travels in a staging database copied to production.
        Site::run('wp option update blog_public 0');

        try {
            expect(productionVerify()['failed'])->toBe(['indexing']);
        } finally {
            Site::run('wp option update blog_public 1');
        }
    });

    it('fails only the salts check on a generated placeholder', function () {
        // Defined, non-empty, and worthless. A check that only looked for emptiness
        // would call this a key.
        $run = productionVerify(['AUTH_SALT' => 'change-me-please']);

        expect($run['failed'])->toBe(['salts']);
        // The report names which of the eight constants had a problem and never a value.
        expect($run['report'])->toContain('AUTH_SALT')->not->toContain('change-me-please');
    });
});

describe('verify --profile=production: the cron heartbeat', function () {
    it('fails when the heartbeat is stale', function () {
        productionHeartbeat('1600000000');

        expect(productionVerify()['failed'])->toBe(['cron-heartbeat']);
    });

    it('fails when the heartbeat is not a timestamp', function () {
        productionHeartbeat('garbage');

        expect(productionVerify()['failed'])->toBe(['cron-heartbeat']);
    });

    it('fails when no heartbeat was ever recorded', function () {
        Site::run('wp option delete foehn_cron_last_run');

        expect(productionVerify()['failed'])->toBe(['cron-heartbeat']);
    });

    it('accepts the heartbeat the Docker runner writes, not autoloaded', function () {
        // The contract between the runner and this profile: one option name, one shape.
        // Written here the way `docker/wordpress/bin/foehn-cron` writes it.
        productionHeartbeat((string) time());

        $autoload = Site::wp(
            'wp eval '
                . escapeshellarg('$o = wp_load_alloptions(); echo isset($o["foehn_cron_last_run"]) ? "yes" : "no";'),
        );

        expect($autoload)->toBe('no');
        expect(productionVerify()['failed'])->toBe([]);
    });
});
