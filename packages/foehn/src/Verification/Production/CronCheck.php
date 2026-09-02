<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Verification\VerificationResult;

/**
 * WordPress's scheduler is real, recent, and keeping up.
 *
 * Three findings from one set of state, which is why they share a class: `real-cron`,
 * `cron-heartbeat` and `cron-backlog` all read the same cadence, and splitting them
 * would mean three copies of what "late" means on this site.
 *
 * The problem being checked is that a broken scheduler is silent. Nothing is due,
 * nothing complains, and the site looks healthy until somebody notices the newsletter
 * has not gone out since March. It gets worse on exactly the stack Føhn is built for: a
 * cache hit is answered without PHP running, so the better the cache works the less
 * often WordPress's pseudo-cron fires, and on a quiet site behind a warm cache it stops
 * altogether. That is why the runtime image turns the pseudo-cron off and runs
 * `wp cron event run --due-now` on a real schedule instead.
 *
 * **No timestamp reaches the report.** The heartbeat is reported as a freshness *state*
 * and the window it was judged against — both the same on two runs of an unchanged site,
 * which a raw age would not be. A verification artifact that changed every time it was
 * written could not be diffed against the last good one, and the specification rules
 * timestamps out for that reason.
 *
 * **What the heartbeat does not prove:** that every scheduled callback succeeded. It says
 * the runner completed a pass. A job that throws every time it runs leaves a fresh
 * heartbeat behind it, and Action Scheduler monitoring stays a separate concern.
 */
final readonly class CronCheck implements Check
{
    public const REAL_CRON = 'real-cron';

    public const HEARTBEAT = 'cron-heartbeat';

    public const BACKLOG = 'cron-backlog';

    /**
     * The grace added to one missed tick before a heartbeat is called stale.
     *
     * The window is `cadence + cadence + GRACE`: busybox's `run-parts` fires on a fixed
     * period and a pass takes as long as the events take, so a deploy landing between
     * two ticks can legitimately see one interval's worth of silence. Two intervals plus
     * five minutes tolerates a single missed or slow tick and still fails a scheduler
     * that has been dead for an hour.
     */
    public const GRACE_SECONDS = 300;

    /**
     * @param bool $pseudoCronDisabled Whether `DISABLE_WP_CRON` is on
     * @param bool $realCronEnabled Whether the runtime's real cron is configured
     * @param mixed $heartbeat The raw option value, so missing and malformed stay distinguishable
     * @param int $cadenceSeconds How often the runner is scheduled to pass
     * @param array<string, int> $overdue Hook name => seconds late, for events past their time
     */
    public function __construct(
        private bool $pseudoCronDisabled,
        private bool $realCronEnabled,
        private mixed $heartbeat,
        private int $cadenceSeconds,
        private array $overdue = [],
    ) {}

    public function run(): array
    {
        return [$this->realCron(), $this->heartbeat(), $this->backlog()];
    }

    /**
     * The longest a heartbeat may be, and the longest an event may be late.
     *
     * One number for both, deliberately: if the runner has been passing on schedule then
     * nothing can be overdue by more than the window in which it should have run, so a
     * backlog older than this and a stale heartbeat are two symptoms of one fault.
     */
    public function window(): int
    {
        return ($this->cadenceSeconds * 2) + self::GRACE_SECONDS;
    }

    /**
     * Both halves of "this site does not rely on visitors to run its scheduler".
     *
     * They are asserted together because either one alone is a misconfiguration.
     * `DISABLE_WP_CRON` without a runner is a site whose events never fire at all — the
     * worst of the three states, and silent. A runner without `DISABLE_WP_CRON` is
     * belt-and-braces rather than broken, but it means every uncached page load still
     * spawns a loopback request, which is the cost the runner was installed to remove.
     */
    private function realCron(): VerificationResult
    {
        $details = [
            'disable_wp_cron' => $this->pseudoCronDisabled,
            'real_cron_enabled' => $this->realCronEnabled,
        ];

        if (!$this->pseudoCronDisabled && !$this->realCronEnabled) {
            return VerificationResult::fail(
                self::REAL_CRON,
                'Neither DISABLE_WP_CRON nor a real cron runner is enabled. Scheduled events '
                . 'depend on visitor traffic, which a page cache removes.',
                $details,
            );
        }

        if (!$this->realCronEnabled) {
            return VerificationResult::fail(
                self::REAL_CRON,
                'DISABLE_WP_CRON is on but no real cron runner is configured. Nothing runs the '
                . 'scheduled events at all.',
                $details,
            );
        }

        if (!$this->pseudoCronDisabled) {
            return VerificationResult::fail(
                self::REAL_CRON,
                'A real cron runner is configured but DISABLE_WP_CRON is off, so every uncached '
                . 'page load still spawns a loopback cron request.',
                $details,
            );
        }

        return VerificationResult::pass(
            self::REAL_CRON,
            'DISABLE_WP_CRON is on and a real cron runner is configured.',
            $details,
        );
    }

    /**
     * Whether the runner has passed recently enough to believe it is still alive.
     */
    private function heartbeat(): VerificationResult
    {
        $window = $this->window();
        $details = ['state' => 'missing', 'maximum_age_seconds' => $window];

        if ($this->heartbeat === false || $this->heartbeat === null || $this->heartbeat === '') {
            return VerificationResult::fail(
                self::HEARTBEAT,
                'No cron heartbeat has ever been recorded. Either the runner has never completed '
                . 'a pass, or nothing is running it.',
                $details,
            );
        }

        if (!is_numeric($this->heartbeat)) {
            // A value that is not a timestamp means whatever wrote it was not the runner.
            // Reported as its own state rather than as "stale": the fix is different, and
            // an operator told "stale" would go looking at cron.
            return VerificationResult::fail(
                self::HEARTBEAT,
                'The cron heartbeat is not a timestamp, so whatever wrote it was not the runner.',
                [...$details, 'state' => 'invalid'],
            );
        }

        $age = max(0, time() - (int) $this->heartbeat);

        if ($age > $window) {
            return VerificationResult::fail(
                self::HEARTBEAT,
                sprintf(
                    'The cron heartbeat is older than the %d seconds this cadence allows. '
                    . 'A scale-to-zero deployment needs an external scheduler recording the same option.',
                    $window,
                ),
                [...$details, 'state' => 'stale'],
            );
        }

        return VerificationResult::pass(
            self::HEARTBEAT,
            'The cron runner completed a pass within the window this cadence allows.',
            [...$details, 'state' => 'fresh'],
        );
    }

    /**
     * Whether anything is late by more than a healthy runner could explain.
     *
     * An event a few seconds past its time is every site between two ticks, so lateness
     * alone says nothing. Past the window, it says the runner is not getting through its
     * queue — which the heartbeat cannot tell you, because a runner that passes promptly
     * while one slow job blocks everything behind it looks perfectly alive.
     */
    private function backlog(): VerificationResult
    {
        $window = $this->window();
        $late = array_keys(array_filter($this->overdue, static fn(int $seconds): bool => $seconds > $window));
        sort($late);

        // Hook names and a count, not how late each one is: seconds change between two
        // runs of an unchanged site, and the report has to be diffable.
        $details = ['overdue' => count($late), 'threshold_seconds' => $window, 'hooks' => $late];

        if ($late !== []) {
            return VerificationResult::fail(
                self::BACKLOG,
                sprintf(
                    '%d scheduled event%s overdue by more than %d seconds. The runner is not '
                    . 'getting through its queue.',
                    count($late),
                    count($late) === 1 ? ' is' : 's are',
                    $window,
                ),
                $details,
            );
        }

        return VerificationResult::pass(
            self::BACKLOG,
            'No scheduled event is overdue beyond the accepted threshold.',
            $details,
        );
    }
}
