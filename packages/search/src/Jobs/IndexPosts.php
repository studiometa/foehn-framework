<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Jobs;

/**
 * Index these posts again: the job one request dispatches for the posts it changed.
 */
final readonly class IndexPosts
{
    /**
     * @param list<int> $ids
     */
    public function __construct(
        public array $ids,
    ) {}
}
