<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Cron\Heartbeat;
use Studiometa\Foehn\Helpers\Env;
use Studiometa\Foehn\Indexing\IndexingProtection;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Security\Salts;
use Studiometa\Foehn\Verification\VerificationProfile;
use Studiometa\Foehn\Verification\VerificationReport;

/**
 * Runs the `production` profile, and is the only place its checks read the world.
 *
 * The split is what makes the profile testable. `WP_DEBUG`, `DISABLE_WP_CRON` and the
 * eight salts are constants, and a constant cannot be varied inside one PHP process —
 * so a check that read them itself could only be tested by spawning a subprocess per
 * fixture, and the specification asks for a fixture per failure. Every check therefore
 * takes plain values through its constructor, and every `defined()`, `getenv()` and
 * `get_option()` in the profile is in this file.
 *
 * The profile owns which checks it runs. There is no flag to run a subset: a release
 * gate that can be narrowed is a release gate that will be narrowed on the day it fails.
 * And it does not adapt to the site it finds — run against staging it fails at the first
 * check, on purpose, because a gate that relaxed its rules when the site said "staging"
 * would wave through a production machine whose environment was simply mislabelled.
 */
final readonly class ProductionChecks
{
    /**
     * How often the runtime image's cron passes, per `FOEHN_CRON_SCHEDULE`.
     *
     * The names are busybox's `/etc/periodic/*` directories, which is what
     * `docker/wordpress/entrypoint.d/85-wp-cron.sh` validates against and symlinks into.
     * A value that is not one of these never gets scheduled at all, so the default here
     * matches the entrypoint's rather than guessing something safer.
     *
     * @var array<string, int>
     */
    public const CADENCES = [
        '15min' => 900,
        'hourly' => 3600,
        'daily' => 86_400,
        'weekly' => 604_800,
        'monthly' => 2_592_000,
    ];

    public const DEFAULT_CADENCE = '15min';

    public function __construct(
        private PageCacheConfig $config,
        private Store $store,
        private IndexingProtection $indexing,
    ) {}

    public function run(): VerificationReport
    {
        $checks = [
            new EnvironmentCheck(Env::get()),
            new DebugCheck(Env::isDebug(), self::flag('WP_DEBUG_DISPLAY')),
            new IndexingCheck(self::wordpressIndexingEnabled(), $this->indexing->isActive()),
            new SaltsCheck(self::salts()),
            new CronCheck(
                self::flag('DISABLE_WP_CRON'),
                self::realCronEnabled(),
                self::rawHeartbeat(),
                self::cadenceSeconds(),
                self::overdueEvents(),
            ),
            new PageCacheCheck($this->config, $this->store),
        ];

        $results = [];

        foreach ($checks as $check) {
            foreach ($check->run() as $result) {
                $results[] = $result;
            }
        }

        return new VerificationReport(VerificationProfile::Production, $results);
    }

    /**
     * A boolean constant, read through `constant()`.
     *
     * The WordPress stubs declare `WP_DEBUG` as literal `false`, which the analyser then
     * folds away — so the bare constant would be a comparison it proves always true.
     */
    private static function flag(string $name): bool
    {
        return defined($name) && (bool) constant($name);
    }

    /**
     * Whether WordPress is willing to be indexed.
     *
     * `blog_public` is core's own switch and it lives in the database, which is how a
     * production site ends up de-indexed by a row nobody remembers writing: staging
     * databases get copied to production and "Discourage search engines" travels with
     * them.
     */
    private static function wordpressIndexingEnabled(): bool
    {
        return function_exists('get_option') && (bool) get_option('blog_public');
    }

    /**
     * The eight keys, or null for each one that is not defined.
     *
     * @return array<string, string|null>
     */
    private static function salts(): array
    {
        $values = [];

        foreach (Salts::NAMES as $name) {
            $values[$name] = defined($name) ? (string) constant($name) : null;
        }

        return $values;
    }

    /**
     * Whether the runtime image's real cron is switched on.
     *
     * The same environment variable the entrypoint exports and the generated
     * `wp-config.php` reads to decide `DISABLE_WP_CRON`, so the two halves of
     * {@see CronCheck}'s first finding come from the same source the runtime uses.
     */
    private static function realCronEnabled(): bool
    {
        $value = getenv('FOEHN_CRON_ENABLED');

        return is_string($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The heartbeat option exactly as stored.
     *
     * Raw rather than through {@see Heartbeat::recordedAt()}, which collapses "never
     * written" and "not a timestamp" into one null. Those are different faults with
     * different fixes, and {@see CronCheck} reports them separately.
     */
    private static function rawHeartbeat(): mixed
    {
        return function_exists('get_option') ? get_option(Heartbeat::OPTION) : false;
    }

    private static function cadenceSeconds(): int
    {
        $schedule = getenv('FOEHN_CRON_SCHEDULE');
        $schedule = is_string($schedule) && $schedule !== '' ? $schedule : self::DEFAULT_CADENCE;

        return self::CADENCES[$schedule] ?? self::CADENCES[self::DEFAULT_CADENCE];
    }

    /**
     * How late each overdue hook is, in seconds, keyed by hook name.
     *
     * `_get_cron_array()` rather than `wp_get_ready_cron_jobs()`: the latter applies
     * core's own gate and answers an empty array when the pseudo-cron is disabled, which
     * on a production site is always. The private function is the only reading of the
     * queue that survives `DISABLE_WP_CRON`.
     *
     * The worst offender per hook is what is kept. A hook scheduled every minute and
     * running late has one entry per missed minute, and reporting all of them would say
     * "four hundred events overdue" about one job.
     *
     * @return array<string, int>
     */
    private static function overdueEvents(): array
    {
        if (!function_exists('_get_cron_array')) {
            return [];
        }

        $now = time();
        $overdue = [];

        foreach (_get_cron_array() as $timestamp => $hooks) {
            $late = $now - (int) $timestamp;

            if ($late <= 0) {
                continue;
            }

            // No shape guard: core declares the queue as `timestamp => hook => key => args`,
            // and a defensive `is_array()` against a declared array is a condition static
            // analysis proves dead — which is a worse thing to carry than the case it
            // imagines.
            foreach (array_keys($hooks) as $hook) {
                $name = (string) $hook;
                $overdue[$name] = max($overdue[$name] ?? 0, $late);
            }
        }

        return $overdue;
    }
}
