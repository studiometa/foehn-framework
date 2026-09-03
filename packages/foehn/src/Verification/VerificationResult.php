<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

/**
 * What one check concluded, in the shape the report serialises.
 *
 * `details` is the check's own evidence and is the only part of the shape a check
 * chooses. Everything a reader or a CI job needs to act — which check, what it
 * decided, one sentence saying why — is in the three fields above it.
 */
final readonly class VerificationResult
{
    /**
     * @param string $name Stable identifier, kebab-case, also the report's sort key
     * @param string $summary One sentence, deterministic: counts rather than timestamps
     * @param array<string, mixed> $details Evidence, already free of absolute paths and secrets
     */
    public function __construct(
        public string $name,
        public VerificationStatus $status,
        public string $summary,
        public array $details = [],
    ) {}

    /**
     * @param array<string, mixed> $details
     */
    public static function pass(string $name, string $summary, array $details = []): self
    {
        return new self($name, VerificationStatus::Pass, $summary, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function fail(string $name, string $summary, array $details = []): self
    {
        return new self($name, VerificationStatus::Fail, $summary, $details);
    }

    /**
     * The check as the report writes it.
     *
     * Keys are listed rather than built, so the JSON key order is a property of this
     * method instead of a property of whatever order a check happened to fill.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'details' => $this->details,
        ];
    }
}
