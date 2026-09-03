<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Updates;

use Studiometa\Foehn\Verification\VerificationFailure;
use Studiometa\Foehn\Verification\VerificationProfile;
use Studiometa\Foehn\Verification\VerificationReport;

/**
 * Runs the `updates` profile and hands back its report.
 *
 * The profile owns which checks it runs, so the command never selects them: there is
 * no flag to run a subset, because a release gate that can be narrowed is a release
 * gate that will be narrowed on the day it fails.
 */
final readonly class UpdatesChecks
{
    public function __construct(
        private RuntimeDiagnosticsCheck $diagnostics,
    ) {}

    /**
     * @throws VerificationFailure When a check could not reach a verdict
     */
    public function run(): VerificationReport
    {
        return new VerificationReport(VerificationProfile::Updates, [$this->diagnostics->run()]);
    }
}
