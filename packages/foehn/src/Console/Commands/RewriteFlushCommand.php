<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\RewriteRuleDiscovery;

#[AsCliCommand(name: 'rewrite:flush', description: 'Rebuild the WordPress rewrite rules', longDescription: <<<'DOC'
    ## DESCRIPTION

    Rebuilds the rewrite rules and forgets the hash Foehn compares them against,
    so the next request registers them from scratch.

    Rules registered in code do nothing until WordPress flushes them once, and
    flushing on every request is a well-known way to ruin a site. Foehn flushes
    when the set of #[AsRewriteRule] declarations changes; this command is for
    when something else has left the rules stale — a plugin that flushed over
    them, a database restored from elsewhere, or a rule that is not matching for
    reasons nothing explains.

    Note that plain permalinks bypass rewrite rules entirely. If the site's
    permalink structure is the default, no flush will make a rule match.

    ## EXAMPLES

        # Rebuild the rules now
        wp foehn rewrite:flush
    DOC)]
final class RewriteFlushCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (get_option('permalink_structure') === '') {
            $this->cli->warning(
                'This site uses plain permalinks, which bypass rewrite rules entirely. '
                . 'Choose another structure under Settings → Permalinks.',
            );
        }

        // The hash goes first: a flush that fails half way leaves the next
        // request registering the rules again, rather than trusting a hash for
        // rules that were never written.
        delete_option(RewriteRuleDiscovery::HASH_OPTION);

        flush_rewrite_rules(false);

        $this->cli->success('Rewrite rules flushed.');
    }
}
