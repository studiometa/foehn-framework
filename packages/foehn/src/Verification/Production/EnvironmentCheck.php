<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Verification\VerificationResult;

/**
 * The site says it is production.
 *
 * The first check, and the one the rest depend on. This profile does not adapt its
 * expectations to the environment it finds: run against staging it fails, on purpose.
 * A deployment gate that relaxed its rules when the site said "staging" would pass a
 * production machine whose `WP_ENVIRONMENT_TYPE` was simply wrong — which is precisely
 * the misconfiguration most worth catching, because every other safeguard in the
 * framework keys off that same value.
 */
final readonly class EnvironmentCheck implements Check
{
    public const NAME = 'environment';

    public const EXPECTED = 'production';

    public function __construct(
        private string $environment,
    ) {}

    public function run(): array
    {
        $details = ['expected' => self::EXPECTED, 'resolved' => $this->environment];

        if ($this->environment !== self::EXPECTED) {
            return [VerificationResult::fail(
                self::NAME,
                sprintf(
                    'WP_ENVIRONMENT_TYPE resolves to %s, not production. '
                    . 'The page cache, the indexing guard and this profile all key off it.',
                    $this->environment,
                ),
                $details,
            )];
        }

        return [VerificationResult::pass(self::NAME, 'WP_ENVIRONMENT_TYPE resolves to production.', $details)];
    }
}
