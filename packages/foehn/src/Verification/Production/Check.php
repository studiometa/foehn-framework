<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Verification\VerificationResult;

/**
 * One production assertion, or a group of them sharing a source of truth.
 *
 * Internal. There is deliberately no attribute, no registry and no discovery behind
 * this: the production profile is a closed list of checks that ships with the
 * framework, and a project cannot add to it. A gate whose contents depend on what a
 * project registered is a gate whose pass means something different on every site.
 *
 * `run()` returns a list rather than one result because some assertions genuinely
 * belong together — {@see CronCheck} reads one set of cron state and has three separate
 * things to say about it, and splitting it into three classes would mean resolving that
 * state three times and keeping three copies of what "overdue" means.
 *
 * Every implementation takes its inputs through its constructor and reads no constant,
 * option or environment variable of its own. That is not style: `WP_DEBUG` and the
 * salts are constants, and a test cannot vary a constant inside one process. Pushing
 * the reading out to {@see ProductionChecks} is what makes every failure case in the
 * specification testable without a subprocess per fixture.
 */
interface Check
{
    /**
     * @return list<VerificationResult>
     */
    public function run(): array;
}
