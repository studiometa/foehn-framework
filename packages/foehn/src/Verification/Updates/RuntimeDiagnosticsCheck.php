<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Updates;

use Studiometa\Foehn\Verification\VerificationFailure;
use Studiometa\Foehn\Verification\VerificationResult;

/**
 * The updates profile's one check: what this process raised.
 *
 * Every actionable diagnostic fails the check, however small. A deprecation notice is
 * a scheduled removal, and the point of running this after a WordPress or plugin
 * update is to see it while the update is still the change under review.
 *
 * Ignored findings — today, only those raised inside the WP-CLI Phar — stay in
 * `details.ignored` and do not fail anything. See {@see DiagnosticsCollector} for what
 * it can and cannot observe; the check reports exactly that and no more.
 */
final readonly class RuntimeDiagnosticsCheck
{
    public const NAME = 'runtime-diagnostics';

    public function __construct(
        private DiagnosticsCollector $collector,
    ) {}

    /**
     * @throws VerificationFailure When the collector never started, so nothing was observed
     */
    public function run(): VerificationResult
    {
        if (!$this->collector->isStarted()) {
            // A pass here would mean "no diagnostics were recorded" while the truth is
            // "nothing was watching". The collector starts in Kernel::bootstrap() under
            // WP-CLI, so this is a broken install rather than a clean site.
            throw new VerificationFailure(
                'The diagnostics collector never started, so this run observed nothing. '
                . 'Check that the theme boots Føhn before the command runs.',
            );
        }

        $diagnostics = $this->collector->diagnostics();
        $ignored = $this->collector->ignored();
        $details = ['diagnostics' => $diagnostics, 'ignored' => $ignored];

        if ($diagnostics === []) {
            return VerificationResult::pass(self::NAME, $this->summary(0, count($ignored)), $details);
        }

        return VerificationResult::fail(
            self::NAME,
            $this->summary(array_sum(array_column($diagnostics, 'count')), count($ignored)),
            $details,
        );
    }

    /**
     * Counts, never a timestamp: the summary is part of a report that has to be
     * byte-identical between two runs of an unchanged site.
     */
    private function summary(int $actionable, int $ignored): string
    {
        $suffix = $ignored === 0 ? '' : sprintf(' %d ignored inside the WP-CLI Phar.', $ignored);

        if ($actionable === 0) {
            return 'No actionable diagnostics in this process.' . $suffix;
        }

        return sprintf(
            '%d actionable diagnostic%s in this process.%s',
            $actionable,
            $actionable === 1 ? '' : 's',
            $suffix,
        );
    }
}
