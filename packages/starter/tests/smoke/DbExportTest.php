<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn db:export`, against the real database of the site.
 *
 * The unit suite proves which table gets which rule and what each anonymizer writes.
 * What only a real database can show is that the dump runs, imports, and has no
 * original email left in it. The import goes to a second database, so the site
 * itself is never touched.
 */

const DB_EXPORT_FILE = '/tmp/foehn-db-export-smoke.sql.gz';

const DB_EXPORT_DATABASE = 'foehn_db_export_smoke';

const DB_EXPORT_EMAIL = 'db-export-smoke@example.org';

/**
 * Run a query as the ddev root user and hand back the last line it printed.
 */
function dbExportQuery(string $sql): string
{
    $result = Site::run(sprintf('mysql -hdb -uroot -proot -N -e %s', escapeshellarg($sql)));
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $result['output'])),
        static fn(string $line): bool => (
            $line !== ''
            && !str_contains($line, 'Deprecated')
            && !str_contains($line, 'Warning')
        ),
    ));

    return $lines === [] ? '' : end($lines);
}

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    Site::run(sprintf(
        'wp user create db-export-smoke %s --role=subscriber --display_name="Jane Smoke"',
        DB_EXPORT_EMAIL,
    ));
});

afterEach(function () {
    Site::run('wp user delete db-export-smoke --yes');
    Site::run('rm -f ' . DB_EXPORT_FILE);
    dbExportQuery('DROP DATABASE IF EXISTS ' . DB_EXPORT_DATABASE);
});

it('exports a dump that imports and holds no original email', function () {
    $export = Site::run('wp foehn db:export ' . DB_EXPORT_FILE);

    expect($export['status'])->toBe(0, $export['output']);

    $found = Site::run(sprintf('zcat %s | grep -c %s', DB_EXPORT_FILE, escapeshellarg(DB_EXPORT_EMAIL)));
    expect(trim($found['output']))->toEndWith('0');

    dbExportQuery('DROP DATABASE IF EXISTS ' . DB_EXPORT_DATABASE . '; CREATE DATABASE ' . DB_EXPORT_DATABASE);
    $import = Site::run(sprintf('zcat %s | mysql -hdb -uroot -proot %s', DB_EXPORT_FILE, DB_EXPORT_DATABASE));
    expect($import['status'])->toBe(0, $import['output']);

    $table = DB_EXPORT_DATABASE . '.wp_users';
    expect((int) dbExportQuery("SELECT COUNT(*) FROM {$table}"))->toBeGreaterThan(0);
    expect(dbExportQuery("SELECT COUNT(*) FROM {$table} WHERE user_email NOT LIKE '%@example.invalid'"))->toBe('0');
    expect(dbExportQuery("SELECT COUNT(*) FROM {$table} WHERE user_pass <> ''"))->toBe('0');
});

it('streams the dump to standard output and the report to standard error', function () {
    $result = Site::run(sprintf(
        'wp foehn db:export - 2>/dev/null > %s && gzip -t %s && echo valid',
        DB_EXPORT_FILE,
        DB_EXPORT_FILE,
    ));

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['output'])->toContain('valid');
});
