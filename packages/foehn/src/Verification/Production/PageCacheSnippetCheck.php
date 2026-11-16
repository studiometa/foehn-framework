<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\PageCache\ServerConfig\InstalledSnippets;
use Studiometa\Foehn\Verification\VerificationResult;

/**
 * Each nginx page-cache snippet of the project carries the policy this release computes.
 *
 * A snippet generated from an earlier config, or by an earlier release, keeps serving
 * hits under rules the site no longer has: other bypass cookies, other query args, other
 * headers. nginx answers HIT, so nothing else shows it. See {@see InstalledSnippets} for
 * where the snippets are looked for.
 *
 * No snippet is a pass: the drop-in serves the cache alone, or the server config is
 * outside the project and out of reach of this check.
 */
final readonly class PageCacheSnippetCheck implements Check
{
    public const NAME = 'page-cache-snippet';

    /**
     * @param array<string, bool> $snippets Whether each snippet is current, by path relative to the project root.
     */
    public function __construct(
        private array $snippets,
    ) {}

    public function run(): array
    {
        $stale = array_keys(array_filter($this->snippets, static fn(bool $current): bool => !$current));
        $details = ['snippets' => array_keys($this->snippets), 'stale' => $stale];

        if ($stale !== []) {
            return [VerificationResult::fail(
                self::NAME,
                sprintf(
                    '%s %s generated from a different config or by an older Føhn release, so nginx serves hits '
                    . 'under rules the site no longer has. Replace it with the output of '
                    . '`wp foehn cache:config --server=nginx`, then reload nginx.',
                    implode(', ', $stale),
                    count($stale) === 1 ? 'was' : 'were',
                ),
                $details,
            )];
        }

        if ($this->snippets === []) {
            return [VerificationResult::pass(
                self::NAME,
                sprintf('No nginx page cache snippet in %s.', implode(' or ', InstalledSnippets::DIRECTORIES)),
                $details,
            )];
        }

        return [VerificationResult::pass(
            self::NAME,
            'The nginx page cache snippet matches the current config.',
            $details,
        )];
    }
}
