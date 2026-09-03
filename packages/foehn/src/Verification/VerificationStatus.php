<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

/**
 * What one check, or a whole report, concluded.
 *
 * `Ignored` is a third state rather than a pass: the finding stays in the report
 * where somebody can read it, and it does not decide the exit status. The updates
 * profile uses it for diagnostics raised inside the WP-CLI Phar, which no project
 * can act on.
 */
enum VerificationStatus: string
{
    case Pass = 'pass';

    case Fail = 'fail';

    case Ignored = 'ignored';
}
