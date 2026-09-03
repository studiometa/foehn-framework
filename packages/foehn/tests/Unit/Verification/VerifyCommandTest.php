<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Indexing\IndexingProtection;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Verification\Production\ProductionChecks;
use Studiometa\Foehn\Verification\ReportRenderer;
use Studiometa\Foehn\Verification\ReportWriter;
use Studiometa\Foehn\Verification\Updates\DiagnosticsCollector;
use Studiometa\Foehn\Verification\Updates\RuntimeDiagnosticsCheck;
use Studiometa\Foehn\Verification\Updates\UpdatesChecks;
use Studiometa\Foehn\Verification\VerifyCommand;

/**
 * The statuses the command asked WP-CLI to exit with.
 *
 * Empty means it never halted, which is how a passing run ends: the command returns and
 * WP-CLI exits `0` on its own.
 *
 * @return list<int>
 */
function verifyExitStatuses(): array
{
    return array_column(array_column(wp_stub_get_calls('wp_cli_halt'), 'args'), 'status');
}

/**
 * Everything the command wrote to the terminal, as one string.
 */
function verifyOutput(): string
{
    return implode("\n", [
        ...array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'),
        ...array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'),
    ]);
}

/**
 * The one error message the command reported, or an empty string.
 */
function verifyError(): string
{
    return implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_error'), 'args'), 'message'));
}

beforeEach(function () {
    wp_stub_reset();

    $this->directory = testVerificationDirectory();
    $this->output = $this->directory . '/foehn-verification.json';

    // A spy handler under the collector, so nothing these tests record can reach
    // PHPUnit's own error handler — this suite runs with failOnWarning. afterEach
    // unwinds the collector and then the spy, leaving the handler stack as found.
    $this->spy = static fn(): bool => true;
    set_error_handler($this->spy);

    $this->collector = new DiagnosticsCollector();
    $this->collector->start();

    $this->cacheRoot = pageCacheRoot();
    $this->pageCache = new PageCacheConfig(enabled: false, path: $this->cacheRoot);

    $this->command = new VerifyCommand(
        new WpCli(),
        new ReportWriter(),
        new ReportRenderer(new WpCli()),
        new UpdatesChecks(new RuntimeDiagnosticsCheck($this->collector)),
        new ProductionChecks($this->pageCache, new Store($this->pageCache), new IndexingProtection()),
    );
});

afterEach(function () {
    $this->collector->stop();
    restore_error_handler();

    @chmod($this->directory, 0o777);
    removeTestDirectory($this->directory);
    removeTestDirectory($this->cacheRoot);
});

describe('verify: the arguments', function () {
    it('refuses a missing profile', function () {
        ($this->command)([], ['output' => $this->output]);

        expect(verifyExitStatuses())->toBe([2]);
        expect(verifyError())->toContain('--profile option is required');
        expect(is_file($this->output))->toBeFalse();
    });

    it('refuses a profile it does not know', function () {
        ($this->command)([], ['profile' => 'staging', 'output' => $this->output]);

        expect(verifyExitStatuses())->toBe([2]);
        expect(verifyError())->toContain("Unknown profile 'staging'");
    });

    it('refuses a flag with no value', function () {
        // `wp foehn verify --profile` hands the command `true`, not a name.
        ($this->command)([], ['profile' => true, 'output' => $this->output]);

        expect(verifyExitStatuses())->toBe([2]);
    });

    it('runs the production profile now that every check it promises exists', function () {
        // It used to be refused with "not available yet", because a gate that ran a
        // subset of the checks its name promises would report a pass nobody should
        // trust. All eight are implemented, so the name resolves.
        ($this->command)([], ['profile' => 'production', 'output' => $this->output]);

        $report = json_decode((string) file_get_contents($this->output), true, flags: JSON_THROW_ON_ERROR);

        expect($report['profile'])->toBe('production');
        expect(array_column($report['checks'], 'name'))->toBe([
            'cron-backlog',
            'cron-heartbeat',
            'debug',
            'environment',
            'indexing',
            'page-cache-storage',
            'real-cron',
            'salts',
        ]);
    });

    it('does not require an output path for production, whose answer is its exit status', function () {
        // A deploy script wants a verdict, not an artifact. Requiring it to nominate a
        // file for a report nothing reads would be carrying another job's paperwork.
        ($this->command)([], ['profile' => 'production']);

        expect(verifyError())->not->toContain('--output option is required');
        expect(verifyOutput())->not->toContain('Report written to');
    });

    it('still writes a production report when one is asked for', function () {
        ($this->command)([], ['profile' => 'production', 'output' => $this->output]);

        expect($this->output)->toBeFile();
        expect(verifyOutput())->toContain('Report written to');
    });

    it('refuses updates without an output path', function () {
        ($this->command)([], ['profile' => 'updates']);

        expect(verifyExitStatuses())->toBe([2]);
        expect(verifyError())->toContain('--output option is required');
    });

    it('refuses a format it cannot render', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output, 'format' => 'yaml']);

        expect(verifyExitStatuses())->toBe([2]);
        expect(verifyError())->toContain('--format option accepts');
    });

    it('reports one problem rather than every problem', function () {
        ($this->command)([], []);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });
});

describe('verify: a clean run', function () {
    it('exits 0 and says so', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        expect(verifyExitStatuses())->toBe([]);
        expect(array_column(array_column(wp_stub_get_calls('wp_cli_success'), 'args'), 'message'))->toBe([
            'Verification passed.',
        ]);
    });

    it('writes the report anyway, so CI has a baseline to diff against', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        /** @var array<string, mixed> $report */
        $report = json_decode((string) file_get_contents($this->output), true);

        expect($report['status'])->toBe('pass');
        expect($report['profile'])->toBe('updates');
        expect($report['checks'][0]['name'])->toBe('runtime-diagnostics');
        expect($report['checks'][0]['details'])->toBe(['diagnostics' => [], 'ignored' => []]);
    });

    it('writes a relative output path under ABSPATH', function () {
        $base = constant('ABSPATH') . 'build';

        if (!is_dir($base)) {
            mkdir($base, 0o777, true);
        }

        try {
            ($this->command)([], ['profile' => 'updates', 'output' => 'build/verify-relative.json']);

            expect($base . '/verify-relative.json')->toBeFile();
        } finally {
            removeTestDirectory($base);
        }
    });
});

describe('verify: a run with something to report', function () {
    beforeEach(function () {
        $this->collector->recordDoingItWrong('wp_enqueue_script', 'Called too early.', '3.3.0');
    });

    it('exits 1', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        expect(verifyExitStatuses())->toBe([1]);
        expect(verifyError())->toContain('Verification failed.');
    });

    it('still writes the report, and names what it found', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        expect($this->output)->toBeFile();

        /** @var array<string, mixed> $report */
        $report = json_decode((string) file_get_contents($this->output), true);

        expect($report['status'])->toBe('fail');
        expect($report['summary'])->toBe(['passed' => 0, 'failed' => 1, 'ignored' => 0]);
        expect($report['checks'][0]['details']['diagnostics'][0])->toMatchArray([
            'type' => 'doing_it_wrong',
            'symbol' => 'wp_enqueue_script',
            'message' => 'Called too early.',
        ]);
    });

    it('does not fail on a diagnostic raised inside the WP-CLI Phar', function () {
        $collector = new DiagnosticsCollector();

        set_error_handler($this->spy);

        try {
            $collector->start();

            try {
                $collector->handleError(E_DEPRECATED, 'vendored', 'phar://wp/php/x.php', 1);

                (new VerifyCommand(
                    new WpCli(),
                    new ReportWriter(),
                    new ReportRenderer(new WpCli()),
                    new UpdatesChecks(new RuntimeDiagnosticsCheck($collector)),
                    new ProductionChecks($this->pageCache, new Store($this->pageCache), new IndexingProtection()),
                ))([], ['profile' => 'updates', 'output' => $this->output]);
            } finally {
                $collector->stop();
            }
        } finally {
            restore_error_handler();
        }

        expect(verifyExitStatuses())->toBe([]);

        /** @var array<string, mixed> $report */
        $report = json_decode((string) file_get_contents($this->output), true);

        expect($report['status'])->toBe('pass');
        expect($report['checks'][0]['details']['ignored'])->toHaveCount(1);
        expect($report['checks'][0]['details']['diagnostics'])->toBe([]);
    });
});

describe('verify: when the run itself cannot answer', function () {
    it('exits 2 when the report cannot be written', function () {
        if (!chmod($this->directory, 0o555) || is_writable($this->directory)) {
            expect(true)->toBeTrue();

            return;
        }

        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        expect(verifyExitStatuses())->toBe([2]);
        expect(verifyError())->toContain('Cannot write the report');
        expect(is_file($this->output))->toBeFalse();
    });

    it('exits 2 when nothing was collecting, rather than reporting a clean run', function () {
        // A pass here would mean "no diagnostics were recorded" while the truth is
        // "nothing was watching".
        $command = new VerifyCommand(
            new WpCli(),
            new ReportWriter(),
            new ReportRenderer(new WpCli()),
            new UpdatesChecks(new RuntimeDiagnosticsCheck(new DiagnosticsCollector())),
            new ProductionChecks($this->pageCache, new Store($this->pageCache), new IndexingProtection()),
        );

        $command([], ['profile' => 'updates', 'output' => $this->output]);

        expect(verifyExitStatuses())->toBe([2]);
        expect(verifyError())->toContain('never started');
        expect(is_file($this->output))->toBeFalse();
    });
});

describe('verify: what it prints', function () {
    it('renders a table by default', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        $printed = verifyOutput();

        expect($printed)->toContain('Verification: updates');
        expect($printed)->toContain('pass');
        expect($printed)->toContain('runtime-diagnostics');
        expect($printed)->toContain('1 passed, 0 failed, 0 ignored.');
    });

    it('prints the evidence behind a failing check', function () {
        $this->collector->recordDoingItWrong('wp_enqueue_script', 'Called too early.', '3.3.0');

        ($this->command)([], ['profile' => 'updates', 'output' => $this->output]);

        $printed = verifyOutput();

        expect($printed)->toContain('Details for runtime-diagnostics');
        expect($printed)->toContain('wp_enqueue_script');
    });

    it('renders the report itself with --format=json', function () {
        ($this->command)([], ['profile' => 'updates', 'output' => $this->output, 'format' => 'json']);

        $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($lines[0] ?? '', true);

        expect($decoded)->not->toBeNull();
        expect($decoded['profile'])->toBe('updates');

        // The terminal rendering and the artifact are the same bytes, so a line copied
        // out of a CI log matches what a later grep of the report will find.
        expect($lines[0] . "\n")->toBe(file_get_contents($this->output));
    });
});
