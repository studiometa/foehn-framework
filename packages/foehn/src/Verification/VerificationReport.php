<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

/**
 * One run of one profile, as the JSON artifact CI keeps.
 *
 * The report is deterministic on purpose: two runs of the same site must produce
 * the same bytes, so that a diff between two CI artifacts is a change in the site
 * rather than a change in the reporting. Three rules buy that:
 *
 * - **Checks are sorted by name**, so the order does not depend on how a profile
 *   assembled them.
 * - **Key order comes from {@see VerificationReport::toArray()}**, not from
 *   insertion order.
 * - **Nothing unstable is in it.** No timestamps, no absolute paths, no stack
 *   traces, no environment URLs, no keys or salts. A freshness claim is reported
 *   as an age or a state, never as the moment the report was written.
 */
final readonly class VerificationReport
{
    /** Bumped when the shape below changes, so a consumer can refuse a report it cannot read. */
    public const SCHEMA = 1;

    /** @var list<VerificationResult> */
    public array $checks;

    /**
     * @param list<VerificationResult> $checks
     */
    public function __construct(
        public VerificationProfile $profile,
        array $checks,
    ) {
        usort($checks, static fn(VerificationResult $a, VerificationResult $b): int => $a->name <=> $b->name);

        $this->checks = array_values($checks);
    }

    /**
     * The run's verdict: one failing check fails the report.
     *
     * An ignored check does not, which is what makes {@see VerificationStatus::Ignored}
     * worth having — a report of nothing but ignored findings still passes.
     */
    public function status(): VerificationStatus
    {
        foreach ($this->checks as $check) {
            if ($check->status === VerificationStatus::Fail) {
                return VerificationStatus::Fail;
            }
        }

        return VerificationStatus::Pass;
    }

    /**
     * @return array{passed: int, failed: int, ignored: int}
     */
    public function summary(): array
    {
        $summary = ['passed' => 0, 'failed' => 0, 'ignored' => 0];

        foreach ($this->checks as $check) {
            $key = match ($check->status) {
                VerificationStatus::Pass => 'passed',
                VerificationStatus::Fail => 'failed',
                VerificationStatus::Ignored => 'ignored',
            };

            $summary[$key]++;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'profile' => $this->profile->value,
            'status' => $this->status()->value,
            'summary' => $this->summary(),
            'checks' => array_map(static fn(VerificationResult $check): array => $check->toArray(), $this->checks),
        ];
    }

    /**
     * The report as the artifact holds it: pretty-printed, unescaped slashes, one
     * trailing newline.
     *
     * Readable rather than compact because the first thing anybody does with a failing
     * CI artifact is open it, and `git diff` on a one-line JSON file says nothing.
     */
    public function toJson(): string
    {
        return (
            json_encode(
                $this->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n"
        );
    }
}
