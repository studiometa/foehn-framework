<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Cron\Heartbeat;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Security\Salts;
use Studiometa\Foehn\Verification\Production\CronCheck;
use Studiometa\Foehn\Verification\Production\DebugCheck;
use Studiometa\Foehn\Verification\Production\EnvironmentCheck;
use Studiometa\Foehn\Verification\Production\IndexingCheck;
use Studiometa\Foehn\Verification\Production\PageCacheCheck;
use Studiometa\Foehn\Verification\Production\SaltsCheck;
use Studiometa\Foehn\Verification\VerificationStatus;

/**
 * The production profile's checks, one fixture per way a deployment can be unsafe.
 *
 * Every check takes its inputs through its constructor rather than reading a constant,
 * which is what makes this file possible: `WP_DEBUG`, `DISABLE_WP_CRON` and the eight
 * salts cannot be varied inside one PHP process, so a check that read them itself could
 * only be tested by spawning a subprocess per fixture. The reading lives in
 * `ProductionChecks`, and what is asserted here is the judgement.
 */

/**
 * The one result a single-result check returns.
 */
function onlyResult(object $check): \Studiometa\Foehn\Verification\VerificationResult
{
    $results = $check->run();

    expect($results)->toHaveCount(1);

    return $results[0];
}

/**
 * Eight distinct, plausible keys — the shape a generated production install has.
 *
 * @return array<string, string>
 */
function realSalts(): array
{
    $values = [];

    foreach (Salts::NAMES as $index => $name) {
        // 64 characters, like WordPress's own generator and `Salts::generate()`, and
        // different per name so the uniqueness rule has something to agree with.
        $values[$name] = str_pad('key' . $index, 64, 'abcdefgh' . $index);
    }

    return $values;
}

beforeEach(function () {
    wp_stub_reset();
    unset($GLOBALS['wp_stub_environment_type']);
});

describe('environment', function () {
    it('passes only when the site says production', function () {
        expect(onlyResult(new EnvironmentCheck('production'))->status)->toBe(VerificationStatus::Pass);
    });

    it('fails on every other environment, rather than adapting to it', function () {
        // The gate does not relax when the site says staging. If it did, a production
        // machine whose WP_ENVIRONMENT_TYPE was simply wrong would sail through — and
        // that is the misconfiguration most worth catching, because the page cache and
        // the indexing guard key off the same value.
        foreach (['staging', 'development', 'local', '', 'Production'] as $environment) {
            $result = onlyResult(new EnvironmentCheck($environment));

            expect($result->status)->toBe(VerificationStatus::Fail, "environment '{$environment}'");
            expect($result->details['resolved'])->toBe($environment);
        }
    });
});

describe('debug', function () {
    it('passes when both constants are off', function () {
        expect(onlyResult(new DebugCheck(false, false))->status)->toBe(VerificationStatus::Pass);
    });

    it('fails on debug, on debug display, and on both', function () {
        foreach ([
            [true,  false, 'WP_DEBUG'],
            [false, true,  'WP_DEBUG_DISPLAY'],
            [true,  true,  'and'],
        ] as $case) {
            [$debug, $display, $expected] = $case;
            $result = onlyResult(new DebugCheck($debug, $display));

            expect($result->status)->toBe(VerificationStatus::Fail);
            expect($result->summary)->toContain($expected);
        }
    });
});

describe('indexing', function () {
    it('passes when WordPress allows indexing and the guard is inactive', function () {
        expect(onlyResult(new IndexingCheck(true, false))->status)->toBe(VerificationStatus::Pass);
    });

    it('fails when WordPress is discouraging search engines', function () {
        // How a production site gets de-indexed by a row nobody remembers writing:
        // staging databases get copied to production and blog_public travels with them.
        $result = onlyResult(new IndexingCheck(false, false));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->summary)->toContain('blog_public');
    });

    it('fails when the non-production guard is active in production', function () {
        $result = onlyResult(new IndexingCheck(true, true));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->summary)->toContain('guard is active');
    });

    it('says both reasons when both hold', function () {
        expect(onlyResult(new IndexingCheck(false, true))->summary)
            ->toContain('blog_public')
            ->toContain('guard is active');
    });

    it('says out loud what it cannot see', function () {
        // A report that implied it had checked the public response would be worse than
        // one that says it did not.
        expect(onlyResult(new IndexingCheck(true, false))->summary)->toContain('CDN');
    });
});

describe('salts', function () {
    it('passes on eight distinct real keys', function () {
        $result = onlyResult(new SaltsCheck(realSalts()));

        expect($result->status)->toBe(VerificationStatus::Pass);
        expect($result->details['usable'])->toBe(8);
    });

    it('fails on a missing key and names it', function () {
        $salts = realSalts();
        unset($salts['NONCE_SALT']);

        $result = onlyResult(new SaltsCheck($salts));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['missing'])->toBe(['NONCE_SALT']);
    });

    it('fails on an empty key, which is not the same as an absent one to a caller', function () {
        $salts = realSalts();
        $salts['AUTH_KEY'] = '   ';

        expect(onlyResult(new SaltsCheck($salts))->details['missing'])->toBe(['AUTH_KEY']);
    });

    it('fails on both generated placeholders, which are defined and non-empty', function () {
        // The case a naive check misses: these are real strings of real length, and a
        // test for emptiness calls them keys.
        $salts = realSalts();
        $salts['AUTH_KEY'] = Salts::PLACEHOLDER_PREFIX . 'auth-key';
        $salts['NONCE_KEY'] = Salts::INSECURE_PREFIX . 'NONCE_KEY-' . md5('somewhere');

        $result = onlyResult(new SaltsCheck($salts));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['placeholder'])->toBe(['AUTH_KEY', 'NONCE_KEY']);
    });

    it('fails on repeated keys and names every name that collides', function () {
        // Eight identical values are one key wearing eight hats. They are separate so a
        // stolen cookie signature is useless in the other seven places.
        $salts = realSalts();
        $salts['AUTH_SALT'] = $salts['AUTH_KEY'];

        $result = onlyResult(new SaltsCheck($salts));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['repeated'])->toBe(['AUTH_KEY', 'AUTH_SALT']);
    });

    it('fails a key too short to be one', function () {
        $salts = realSalts();
        $salts['LOGGED_IN_KEY'] = 'x';

        expect(onlyResult(new SaltsCheck($salts))->details['too_short'])->toBe(['LOGGED_IN_KEY']);
    });

    it('never puts a value, a fragment of one, or its length in the report', function () {
        // A verification artifact is a file CI keeps and attaches to a build. A length is
        // a hint about a secret, so not even that goes in.
        $salts = realSalts();
        $salts['AUTH_SALT'] = $salts['AUTH_KEY'];
        $salts['NONCE_KEY'] = Salts::PLACEHOLDER_PREFIX . 'nonce';

        $result = onlyResult(new SaltsCheck($salts));
        $serialized = json_encode($result->toArray(), JSON_THROW_ON_ERROR);

        foreach ($salts as $value) {
            expect($serialized)->not->toContain($value);
            // A prefix long enough to be a crib, in case a future version truncated.
            expect($serialized)->not->toContain(substr($value, 0, 12));
        }

        expect(array_keys($result->details))->toBe([
            'expected',
            'usable',
            'missing',
            'placeholder',
            'too_short',
            'repeated',
        ]);
    });
});

describe('real cron', function () {
    $cron = static fn(bool $disabled, bool $real): CronCheck => new CronCheck($disabled, $real, time(), 900);

    it('passes when the pseudo-cron is off and a runner is configured', function () use ($cron) {
        expect($cron(true, true)->run()[0]->status)->toBe(VerificationStatus::Pass);
    });

    it('fails hardest when the pseudo-cron is off and nothing runs the events', function () use ($cron) {
        // The worst of the three states, and silent: DISABLE_WP_CRON with no runner
        // means the events never fire at all.
        $result = $cron(true, false)->run()[0];

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->summary)->toContain('Nothing runs the');
    });

    it('fails when a runner exists but the pseudo-cron was left on', function () use ($cron) {
        $result = $cron(false, true)->run()[0];

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->summary)->toContain('loopback');
    });

    it('fails when neither is configured', function () use ($cron) {
        expect($cron(false, false)->run()[0]->summary)->toContain('Neither');
    });
});

describe('cron heartbeat', function () {
    $heartbeat = static fn(mixed $value): \Studiometa\Foehn\Verification\VerificationResult => new CronCheck(
        true,
        true,
        $value,
        900,
    )->run()[1];

    it('passes a heartbeat inside the window this cadence allows', function () use ($heartbeat) {
        $result = $heartbeat(time() - 60);

        expect($result->status)->toBe(VerificationStatus::Pass);
        expect($result->details['state'])->toBe('fresh');
    });

    it('passes a heartbeat that is late by less than one missed tick plus the grace', function () use ($heartbeat) {
        // 15 minutes twice plus five minutes: a deploy landing between two ticks can
        // legitimately see one interval of silence, and this must not reject it.
        expect($heartbeat(time() - ((2 * 900) + CronCheck::GRACE_SECONDS - 30))->details['state'])->toBe('fresh');
    });

    it('fails a heartbeat past the window', function () use ($heartbeat) {
        $result = $heartbeat(time() - 7200);

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['state'])->toBe('stale');
        expect($result->summary)->toContain('scale-to-zero');
    });

    it('fails a heartbeat that was never written', function () use ($heartbeat) {
        foreach ([false, null, ''] as $absent) {
            $result = $heartbeat($absent);

            expect($result->status)->toBe(VerificationStatus::Fail);
            expect($result->details['state'])->toBe('missing');
        }
    });

    it('reports a non-timestamp as its own fault, not as staleness', function () use ($heartbeat) {
        // The fix is different, and an operator told "stale" would go looking at cron.
        $result = $heartbeat('not-a-timestamp');

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['state'])->toBe('invalid');
    });

    it('does not treat a clock that ran backwards as a run in the future', function () use ($heartbeat) {
        expect($heartbeat(time() + 3600)->details['state'])->toBe('fresh');
    });

    it('puts no timestamp and no age in the report, only a state and the window', function () {
        // A raw age changes on every run, so an artifact carrying one could not be
        // diffed against the last good one. The specification rules out timestamps for
        // the same reason.
        $recorded = time() - 60;
        $result = new CronCheck(true, true, $recorded, 900)->run()[1];

        expect(array_keys($result->details))->toBe(['state', 'maximum_age_seconds']);
        expect(json_encode($result->toArray(), JSON_THROW_ON_ERROR))->not->toContain((string) $recorded);
    });

    it('widens the window with the configured cadence', function () {
        $stale = time() - 7200;

        expect(new CronCheck(true, true, $stale, 900)->run()[1]->details['state'])->toBe('stale');
        // Hourly: two hours plus the grace, so the same value is still fresh.
        expect(new CronCheck(true, true, $stale, 3600)->run()[1]->details['state'])->toBe('fresh');
    });
});

describe('cron backlog', function () {
    it('passes when nothing is late beyond the threshold', function () {
        // Every site between two ticks has something a little late. Lateness alone says
        // nothing, which is why the threshold exists.
        $result = new CronCheck(true, true, time(), 900, ['wp_version_check' => 120])->run()[2];

        expect($result->status)->toBe(VerificationStatus::Pass);
        expect($result->details['overdue'])->toBe(0);
    });

    it('fails when an event is overdue past the threshold, and names the hooks', function () {
        // What the heartbeat cannot tell you: a runner passing promptly while one slow
        // job blocks everything behind it looks perfectly alive.
        $result = new CronCheck(true, true, time(), 900, [
            'action_scheduler_run_queue' => 86400,
            'wp_version_check' => 60,
            'foehn_sweep' => 90000,
        ])->run()[2];

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['overdue'])->toBe(2);
        expect($result->details['hooks'])->toBe(['action_scheduler_run_queue', 'foehn_sweep']);
    });

    it('reports the hooks sorted, so two runs of one site produce one report', function () {
        $result = new CronCheck(true, true, time(), 900, ['zzz' => 90000, 'aaa' => 90000])->run()[2];

        expect($result->details['hooks'])->toBe(['aaa', 'zzz']);
    });

    it('puts no lateness in seconds in the report', function () {
        $result = new CronCheck(true, true, time(), 900, ['slow' => 86400])->run()[2];

        expect(json_encode($result->toArray(), JSON_THROW_ON_ERROR))->not->toContain('86400');
    });
});

describe('page cache storage', function () {
    beforeEach(function () {
        $this->root = pageCacheRoot();
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('passes an active cache whose storage is writable', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->root);
        $result = onlyResult(new PageCacheCheck($config, new Store($config)));

        expect($result->status)->toBe(VerificationStatus::Pass);
        expect($result->details)->toBe([
            'configured' => true,
            'effective' => true,
            'root_contained' => true,
            'root_writable' => true,
        ]);
    });

    it('passes a disabled cache, and reports what an earlier release left behind', function () {
        // A project is allowed not to want a page cache. What the gate has to say is that
        // the stored pages are still there and still clearable.
        $config = new PageCacheConfig(enabled: false, path: $this->root);
        $store = new Store($config);
        $store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        $store->put(CacheKey::create('example.com', '/blog/'), '<html>blog</html>');

        $result = onlyResult(new PageCacheCheck($config, $store));

        expect($result->status)->toBe(VerificationStatus::Pass);
        expect($result->details['stale_pages'])->toBe(2);
        expect($result->summary)->toContain('cache:clear');
    });

    it('passes a disabled cache with nothing in it, without mentioning stale pages', function () {
        $config = new PageCacheConfig(enabled: false, path: $this->root);

        expect(onlyResult(new PageCacheCheck($config, new Store($config)))->summary)
            ->toBe('The page cache is disabled and nothing is stored.');
    });

    it('fails a cache enabled but excluded from production by its own config', function () {
        // The setting says yes and the site it is deployed to is excluded, so nothing is
        // cached and nobody said so.
        $config = new PageCacheConfig(enabled: true, path: $this->root, environments: ['staging']);
        $result = onlyResult(new PageCacheCheck($config, new Store($config)));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->summary)->toContain('excludes production');
    });

    it('fails an enabled cache whose directory cannot be written', function () {
        // The fault this check exists for, and it is silent: the site stays correct and
        // renders every page every time. Usually root-owned files from WP-CLI run as root.
        mkdir($this->root, 0o555, true);

        $config = new PageCacheConfig(enabled: true, path: $this->root);
        $result = onlyResult(new PageCacheCheck($config, new Store($config)));

        expect($result->status)->toBe(VerificationStatus::Fail);
        expect($result->details['root_writable'])->toBeFalse();
        expect($result->summary)->toContain('root-owned');

        chmod($this->root, 0o755);
    })->skipOnWindows();

    it('accepts a root that does not exist yet, because the first request creates it', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->root . '/not/created/yet');

        expect(onlyResult(new PageCacheCheck($config, new Store($config)))->status)->toBe(VerificationStatus::Pass);
    });

    it('puts no filesystem path in the report', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->root);
        $result = onlyResult(new PageCacheCheck($config, new Store($config)));

        expect(json_encode($result->toArray(), JSON_THROW_ON_ERROR))->not->toContain($this->root);
    });
});

describe('the heartbeat option both halves read', function () {
    it('is the one name the runner writes and verification reads', function () {
        // Stated once, in Cron\Heartbeat, so the Docker runner and this profile cannot
        // drift onto two option names — which would make a healthy site fail the gate.
        expect(Heartbeat::OPTION)->toBe('foehn_cron_last_run');
    });
});
