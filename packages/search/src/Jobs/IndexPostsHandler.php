<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Jobs;

use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Search\Indexer;

#[AsJob(group: 'foehn-search')]
final readonly class IndexPostsHandler
{
    public function __construct(
        private Indexer $indexer,
    ) {}

    public function __invoke(IndexPosts $job): void
    {
        foreach ($job->ids as $id) {
            $this->indexer->index((int) $id);
        }
    }
}
