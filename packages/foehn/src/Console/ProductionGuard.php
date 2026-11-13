<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Helpers\Env;

/**
 * Stops a command that writes from running in production by accident.
 *
 * A command declared with `#[AsCliCommand(writes: true)]` changes content or files:
 * `wp foehn seed` writes posts, the `make:` commands write classes. Run against the
 * production site — a coding agent on the wrong SSH host, a shell left open — they
 * change what visitors see. So in the production environment they run only when the
 * caller says so with `--force`, or with `--dry-run`, which writes nothing.
 *
 * CliCommandDiscovery asks before it builds the command, so no command carries this
 * check itself. Commands that only read, and the cache commands a deploy runs, do not
 * declare `writes` and are never stopped.
 */
final readonly class ProductionGuard
{
    public function __construct(
        private WpCli $cli,
    ) {}

    /**
     * Whether the command may run; when it may not, say why and how, and end with an error.
     *
     * @param string $commandName The command as WP-CLI registers it, with its namespace: `app import:products`
     * @param array<string, mixed> $assocArgs The command's named arguments
     */
    public function allows(string $commandName, AsCliCommand $command, array $assocArgs): bool
    {
        if (!$command->writes || !Env::isProduction()) {
            return true;
        }

        if (($assocArgs['force'] ?? null) !== null || ($assocArgs['dry-run'] ?? null) !== null) {
            return true;
        }

        // Only offered when the command takes it: WP-CLI rejects an option the long
        // description does not declare.
        $how = str_contains($command->longDescription ?? '', '[--dry-run]')
            ? 'Run it on a local or staging copy, add --dry-run to see what it would do, or add --force to run it here.'
            : 'Run it on a local or staging copy, or add --force to run it here.';

        $this->cli->error(sprintf(
            "`wp %s` changes content or files, and wp_get_environment_type() is 'production', so it did not run. %s",
            $commandName,
            $how,
        ));

        return false;
    }
}
