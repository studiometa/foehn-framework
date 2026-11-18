<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Search\Indexer;
use Studiometa\Foehn\Search\SearchDocuments;

#[AsCliCommand(name: 'reindex', description: 'Rebuild the search indexes', longDescription: <<<'DOC'
    ## DESCRIPTION

    Empties the index of each language and indexes every published post of the post
    types that have an #[AsSearchDocument] class.

    Run it after the first install, after a change of the document classes or of the
    searchable attributes, and on each machine of a host that has several.

    ## EXAMPLES

        wp foehn-search reindex
    DOC)]
final readonly class ReindexCommand implements CliCommandInterface
{
    public function __construct(
        private WpCli $cli,
        private Indexer $indexer,
        private SearchDocuments $documents,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if ($this->documents->postTypes() === []) {
            $this->cli->warning('No class has #[AsSearchDocument]: there is nothing to index.');

            return;
        }

        foreach ($this->indexer->rebuild() as $language => $count) {
            $this->cli->log(sprintf('%s: %d document%s', $language, $count, $count === 1 ? '' : 's'));
        }

        $this->cli->success('Search indexes rebuilt.');
    }
}
