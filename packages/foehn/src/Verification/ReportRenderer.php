<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

use Studiometa\Foehn\Console\WpCli;

/**
 * The report as a person reads it in a CI log.
 *
 * Terminal output only. `--output` writes the artifact other software consumes, and
 * the two are deliberately separate: this rendering can be made friendlier without
 * that being a schema change.
 */
final readonly class ReportRenderer
{
    /** @var list<string> */
    public const FORMATS = ['table', 'json'];

    public function __construct(
        private WpCli $cli,
    ) {}

    /**
     * @param string $format One of {@see ReportRenderer::FORMATS}; anything else renders as a table
     */
    public function render(VerificationReport $report, string $format): void
    {
        if ($format === 'json') {
            $this->cli->line(rtrim($report->toJson(), "\n"));

            return;
        }

        $this->renderTable($report);
    }

    /**
     * One row per check, then the evidence behind each failure.
     *
     * Details are printed for failing checks only. A passing check's evidence is
     * noise in a log that is read when something broke — and it is in the report
     * either way, which is where anybody looking for it should go.
     */
    private function renderTable(VerificationReport $report): void
    {
        $this->cli->line("Verification: {$report->profile->value}");
        $this->cli->line(str_repeat('=', strlen($report->profile->value) + 14));
        $this->cli->line('');

        $width = 0;

        foreach ($report->checks as $check) {
            $width = max($width, strlen($check->name));
        }

        foreach ($report->checks as $check) {
            $this->cli->log(sprintf(
                '%-7s %s  %s',
                $check->status->value,
                str_pad($check->name, $width),
                $check->summary,
            ));
        }

        foreach ($report->checks as $check) {
            if ($check->status !== VerificationStatus::Fail || $check->details === []) {
                continue;
            }

            $this->cli->line('');
            $this->cli->log("Details for {$check->name}:");

            foreach (explode("\n", $this->details($check)) as $line) {
                $this->cli->line('  ' . $line);
            }
        }

        $summary = $report->summary();

        $this->cli->line('');
        $this->cli->log(sprintf(
            '%d passed, %d failed, %d ignored.',
            $summary['passed'],
            $summary['failed'],
            $summary['ignored'],
        ));
    }

    /**
     * A check's evidence, as the report holds it.
     *
     * Printed as JSON rather than reformatted per check: what a reader needs from a
     * failing CI log is the same bytes they would find in the artifact, so that a
     * copied line matches what a later grep of the report will find.
     */
    private function details(VerificationResult $check): string
    {
        return (string) json_encode(
            $check->details,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
