<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

use RuntimeException;

/**
 * Verification could not reach a verdict.
 *
 * Distinct from a failing check, and it exits `2` rather than `1`: a report that
 * could not be written, or a collector that never started, tells CI nothing about
 * the site. Treating that as a clean failure would let an unusable run look like
 * an inspected one.
 */
final class VerificationFailure extends RuntimeException {}
