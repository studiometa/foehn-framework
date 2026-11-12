<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

use Druidfi\Mysqldump\Compress\CompressManagerFactory;
use Druidfi\Mysqldump\Mysqldump;
use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\DatabaseExportConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Helpers\WP;
use Throwable;

#[AsCliCommand(
    name: 'db:export',
    description: 'Export a light, anonymized copy of the database',
    longDescription: <<<'DOC'
        ## DESCRIPTION

        Writes a gzipped SQL dump of the tables with the WordPress table prefix, to
        import on a local copy of the site. Run it on production, where the data is.

        Content tables are kept. Users, user meta and comments are anonymized:
        emails become `user-<id>@example.invalid`, logins `user-<id>`, password hashes
        are removed. Transients are left out, and revisions and WooCommerce orders
        older than the window are left out.

        A table no rule covers is exported without rows, and is listed at the end.
        Declare it in `app/db-export.config.php` (DatabaseExportConfig).

        Post content is not changed: it can hold personal data.

        Progress and the report go to standard error, so `-` gives a clean stream.

        ## OPTIONS

        [<file>]
        : Where to write the dump. `-` writes it to standard output. Defaults to
          `<database>-<date>.sql.gz` in the current directory.

        [--from=<date>]
        : The start of the window for revisions and orders. A date or a relative date
          such as `-2 weeks`. Defaults to `DatabaseExportConfig::$window` (`-2 months`).

        ## EXAMPLES

            # Write a file
            wp foehn db:export

            # Stream it from production to a local file
            ssh production 'wp foehn db:export -' > db.sql.gz

            # Keep one year of revisions and orders
            wp foehn db:export db.sql.gz --from=-1year
        DOC,
)]
final readonly class DbExportCommand implements CliCommandInterface
{
    public function __construct(
        private WpCli $cli,
        private DatabaseExportConfig $config,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $database = (string) constant('DB_NAME');
        $target = $args[0] ?? sprintf('%s-%s.sql.gz', $database, gmdate('Ymd-His'));
        $toStdout = $target === '-';

        if ($toStdout) {
            // A notice printed to standard output would corrupt the stream.
            // @mago-expect lint:no-ini-set
            ini_set('display_errors', 'stderr');
        }

        try {
            $from = $this->config->from($assocArgs['from'] ?? null);
        } catch (InvalidArgumentException $exception) {
            $this->cli->error($exception->getMessage());

            return;
        }

        $db = WP::db();
        $prefix = $db->base_prefix;
        // Only this site's tables: a database can hold several sites.
        $tables = array_values(array_filter(
            array_map(strval(...), $db->get_col("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")),
            static fn(string $table): bool => str_starts_with($table, $prefix),
        ));

        $plan = ExportPlan::resolve($tables, $prefix, $from, $this->config);
        $rows = [];

        try {
            $dump = new Mysqldump(
                Dsn::fromWordPress((string) constant('DB_HOST'), $database),
                (string) constant('DB_USER'),
                (string) constant('DB_PASSWORD'),
                [
                    'include-tables' => $tables,
                    'no-data' => $plan->structureOnly(),
                    'compress' => CompressManagerFactory::GZIP,
                    'default-character-set' => 'utf8mb4',
                    'add-drop-table' => true,
                    'single-transaction' => true,
                    'lock-tables' => false,
                    'skip-definer' => true,
                ],
            );
            $dump->setTableWheres($plan->wheres());
            $dump->setTransformTableRowHook(new RowTransformer($plan->tables, $this->config));
            $dump->setInfoHook(function (string $object, array $info) use (&$rows, $plan): void {
                if ($object !== 'table' || !($info['completed'] ?? false)) {
                    return;
                }

                $table = (string) $info['name'];
                $rows[$table] = (int) $info['rowCount'];
                $this->cli->stderr(sprintf(
                    '  %s: %d rows, %s',
                    $table,
                    $rows[$table],
                    ($plan->tables[$table] ?? null)?->describe() ?? 'all rows',
                ));
            });

            $this->cli->stderr(sprintf('Exporting %d tables (window from %s UTC)', count($tables), $from));
            $dump->start($toStdout ? 'php://stdout' : $target);
        } catch (Throwable $exception) {
            $this->cli->error('The export failed: ' . $exception->getMessage());

            return;
        }

        $this->report($plan, $rows, $toStdout ? null : $target);
    }

    /**
     * @param array<string, int> $rows
     */
    private function report(ExportPlan $plan, array $rows, ?string $file): void
    {
        $this->cli->stderr('');

        if ($plan->presets !== []) {
            $this->cli->stderr('Presets: ' . implode(', ', $plan->presets));
        }

        $declared = array_diff($plan->structureOnly(), $plan->uncovered);

        if ($declared !== []) {
            $this->cli->stderr('Structure only: ' . implode(', ', $declared));
        }

        if ($plan->uncovered !== []) {
            $this->cli->warning(sprintf(
                'No rule covers %d tables, so they were exported without rows: %s. Declare them in app/db-export.config.php.',
                count($plan->uncovered),
                implode(', ', $plan->uncovered),
            ));
        }

        $this->cli->stderr('');
        $this->cli->stderr(sprintf(
            'Exported %d rows%s.',
            array_sum($rows),
            $file !== null && is_file($file)
                ? sprintf(' to %s (%s bytes)', $file, number_format((int) filesize($file)))
                : '',
        ));
    }
}
