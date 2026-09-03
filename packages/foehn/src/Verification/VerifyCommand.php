<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Verification\Production\ProductionChecks;
use Studiometa\Foehn\Verification\Updates\UpdatesChecks;

#[AsCliCommand(
    name: 'verify',
    description: 'Run a verification profile and report its result',
    longDescription: <<<'DOC'
        ## DESCRIPTION

        Runs one named verification profile against the booted site, writes a
        deterministic JSON report, and exits with a status a release gate can act on.

        The profile is required: a gate that defaulted to something would eventually run
        the wrong checks silently.

        ## OPTIONS

        [--profile=<profile>]
        : Which gate to run. Required. `updates` for CI after a WordPress or plugin
        update; `production` for the deployment script.

        [--output=<path>]
        : Where to write the JSON report. Required for `updates`, optional for
        `production`. Relative paths resolve from ABSPATH. The file is written
        atomically, on a pass and on a failure alike.

        [--format=<format>]
        : Terminal output only: `table` (default) or `json`. `--output` is the artifact
        other software should read.

        ## EXIT STATUS

        - 0: the profile passed
        - 1: actionable diagnostics, or a failed check
        - 2: invalid arguments, a report that could not be written, or verification that
        could not reach a verdict

        A WordPress that cannot boot fails before this command exists, so the surrounding
        CI or deployment script must treat a missing command or a failed boot as an
        infrastructure failure of its own.

        ## EXAMPLES

            # CI, after a WordPress core or plugin update
            wp foehn verify --profile=updates --output=build/foehn-verification.json

            # The deployment script, against the booted production site
            wp foehn verify --profile=production
        DOC,
)]
final readonly class VerifyCommand implements CliCommandInterface
{
    /** The site has something to act on. */
    private const EXIT_FAIL = 1;

    /**
     * The run said nothing about the site: bad arguments, an unwritable report, a
     * verdict that could not be reached. Deliberately distinct from `1`, so a CI job
     * can tell "your update broke something" from "this gate did not run".
     */
    private const EXIT_INFRASTRUCTURE = 2;

    public function __construct(
        private WpCli $cli,
        private ReportWriter $writer,
        private ReportRenderer $renderer,
        private UpdatesChecks $updates,
        private ProductionChecks $production,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $profile = $this->profile($assocArgs);

        if ($profile === null) {
            return;
        }

        $format = $this->format($assocArgs);

        if ($format === null) {
            return;
        }

        $output = $this->output($assocArgs, $profile);

        if ($output === false) {
            return;
        }

        try {
            $report = $profile === VerificationProfile::Production ? $this->production->run() : $this->updates->run();

            // Rendered before it is written, so a run whose report cannot be written
            // still says what it found. Written on a pass as well as a failure: a CI job
            // that only keeps failing artifacts cannot diff against the last good one.
            $this->renderer->render($report, $format);

            $path = $output === null ? null : $this->writer->write($output, $report);
        } catch (VerificationFailure $failure) {
            $this->fail($failure->getMessage());

            return;
        }

        if ($path !== null) {
            $this->cli->log("Report written to {$path}");
        }

        if ($report->status() === VerificationStatus::Fail) {
            $this->cli->error('Verification failed.', exit: false);
            $this->cli->halt(self::EXIT_FAIL);

            return;
        }

        // No halt on a pass: the command returns and WP-CLI exits 0 through its own
        // shutdown, so `after_invoke` hooks and output buffers behave as they do for
        // every other command.
        $this->cli->success('Verification passed.');
    }

    /**
     * The selected profile, or null after reporting why there is none.
     *
     * A profile named in the specification but not built yet is refused with its own
     * message. It has to be: a gate that ran a subset of the checks its name promises
     * would report a pass nobody should trust, so the profile does not exist until
     * every one of its checks does.
     *
     * @param array<string, mixed> $assocArgs
     */
    private function profile(array $assocArgs): ?VerificationProfile
    {
        $requested = $assocArgs['profile'] ?? null;
        $available = implode(', ', VerificationProfile::names());

        if (!is_string($requested) || $requested === '') {
            $this->fail("The --profile option is required. Available profiles: {$available}.");

            return null;
        }

        $planned = VerificationProfile::PLANNED[$requested] ?? null;

        if ($planned !== null) {
            $this->fail(sprintf(
                "The '%s' profile is not available yet — it arrives with %s. Available profiles: %s.",
                $requested,
                $planned,
                $available,
            ));

            return null;
        }

        $profile = VerificationProfile::tryFrom($requested);

        if ($profile === null) {
            $this->fail("Unknown profile '{$requested}'. Available profiles: {$available}.");
        }

        return $profile;
    }

    /**
     * @param array<string, mixed> $assocArgs
     */
    private function format(array $assocArgs): ?string
    {
        $format = $assocArgs['format'] ?? 'table';

        if (!is_string($format) || !in_array($format, ReportRenderer::FORMATS, true)) {
            $this->fail(sprintf('The --format option accepts %s.', implode(' or ', ReportRenderer::FORMATS)));

            return null;
        }

        return $format;
    }

    /**
     * Where to write the report: a path, `null` for nowhere, or `false` after refusing.
     *
     * Three answers rather than two, because "no path was given" is legitimate for one
     * profile and an argument error for the other. `updates` exists to hand CI an
     * artifact — a run whose findings only reached a terminal is a run nobody can attach
     * to the update it was reviewing — while `production` is a deployment gate whose
     * answer is its exit status, and a deploy script that had to nominate a file for a
     * report nothing reads would be carrying the artifact of a different job.
     *
     * @param array<string, mixed> $assocArgs
     * @return string|null|false
     */
    private function output(array $assocArgs, VerificationProfile $profile): string|false|null
    {
        $output = $assocArgs['output'] ?? null;

        if (is_string($output) && $output !== '') {
            return $output;
        }

        if ($profile !== VerificationProfile::Updates) {
            return null;
        }

        $this->fail('The --output option is required for the updates profile.');

        return false;
    }

    /**
     * Report an infrastructure failure and exit `2`.
     *
     * `error()` with `exit: false` so the message goes to stderr the way every other
     * WP-CLI error does, and `halt()` for the status, since `error()` can only ever
     * exit `1`.
     */
    private function fail(string $message): void
    {
        $this->cli->error($message, exit: false);
        $this->cli->halt(self::EXIT_INFRASTRUCTURE);
    }
}
