<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Verification\VerificationResult;

/**
 * The site can be indexed, and nothing in Føhn is stopping it.
 *
 * Two halves, and they fail for opposite reasons.
 *
 * `blog_public` is WordPress's own switch and it lives in the database, which is how a
 * production site comes to be de-indexed by a row nobody remembers writing: staging
 * databases get copied to production, and "Discourage search engines" travels with
 * them. Føhn's own guard deliberately never writes that option — see
 * {@see \Studiometa\Foehn\Indexing\IndexingProtection} — precisely so that this check
 * has something meaningful to read.
 *
 * The guard being inactive is the other half, and on a correctly configured production
 * site it follows from {@see EnvironmentCheck} passing. It is asserted separately anyway
 * because "the environment says production" and "the module agrees it is production" are
 * two statements, and a report that only made the first would not notice the day they
 * stop matching.
 *
 * **Limit, stated in the report's own summary as well as here:** this reads WordPress and
 * Føhn. It cannot prove that a CDN or web server is not adding an `X-Robots-Tag` of its
 * own, and it cannot see a physical `robots.txt` sitting in front of WordPress. A
 * deployment that needs that guarantee has to inspect the public HTTP response.
 */
final readonly class IndexingCheck implements Check
{
    public const NAME = 'indexing';

    public function __construct(
        private bool $wordpressIndexingEnabled,
        private bool $protectionActive,
    ) {}

    public function run(): array
    {
        $details = [
            'wordpress_indexing_enabled' => $this->wordpressIndexingEnabled,
            'foehn_protection_active' => $this->protectionActive,
        ];

        $reasons = [];

        if (!$this->wordpressIndexingEnabled) {
            $reasons[] = 'WordPress is discouraging search engines (blog_public is off)';
        }

        if ($this->protectionActive) {
            $reasons[] = "Føhn's non-production indexing guard is active";
        }

        if ($reasons !== []) {
            return [VerificationResult::fail(
                self::NAME,
                sprintf('%s. The site is not indexable.', implode(', and ', $reasons)),
                $details,
            )];
        }

        return [VerificationResult::pass(
            self::NAME,
            'WordPress indexing is enabled and the non-production guard is inactive. '
            . 'A CDN or web-server header is outside what this can see.',
            $details,
        )];
    }
}
