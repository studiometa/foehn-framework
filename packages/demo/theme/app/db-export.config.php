<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\DatabaseExportConfig;

/**
 * What `wp foehn db:export` keeps of the demo's database.
 *
 * The demo's own dump, database/demo.sql.gz, is made with that command. See
 * database/README.md.
 */
return new DatabaseExportConfig(
    // Action Scheduler's tables: the queue and the log of one machine. Discovery
    // schedules the cron jobs again on the first request of the restored site.
    structureOnlyTables: ['actionscheduler_*'],
    // The seeded administrator is admin@example.com, so the dump keeps its `admin`
    // login. Its password hash never leaves: database/restore.sh sets one.
    allowedEmailDomains: ['example.com'],
);
