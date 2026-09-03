<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Security\Salts;
use Studiometa\Foehn\Verification\VerificationResult;

/**
 * All eight WordPress keys exist, are real, and are eight different values.
 *
 * These sign authentication cookies and nonces, so a site whose keys are guessable is a
 * site whose login cookies can be forged. Four ways that goes wrong, and each one is a
 * separate finding because each has a different fix:
 *
 * - **Missing.** `wp foehn salts:generate`. The generated `wp-config.php` already
 *   refuses to serve a production request in this state, so it should be unreachable —
 *   but only if nobody has edited that file.
 * - **A placeholder.** `change-me-…` from a `.env` nobody filled in, or
 *   `insecure-development-key-…` which the generated config derives on purpose outside
 *   production. Both are defined, non-empty, and worthless; a check that only looked
 *   for emptiness would call them keys.
 * - **Repeated.** Eight identical values are one key wearing eight hats: they are
 *   separate so that a stolen cookie signature is useless in the other seven places.
 *   This is what copy-paste into a `.env` produces.
 * - **Too short to be a key.** A one-character value passes every test above.
 *
 * **The report never contains a value.** Not truncated, not hashed, not a length —
 * a length is a hint about a secret, and a verification artifact is a file CI keeps and
 * attaches to a build. What it contains is the names, which are already public: the
 * eight constants WordPress documents.
 */
final readonly class SaltsCheck implements Check
{
    public const NAME = 'salts';

    /**
     * Shorter than this is not a key, whatever it is.
     *
     * Well below the 64 characters WordPress's own generator produces and the 64 that
     * `Salts::generate()` produces, so this catches a truncated paste and a placeholder
     * somebody shortened rather than second-guessing a project that brings its own.
     */
    private const MINIMUM_LENGTH = 32;

    /**
     * @param array<string, string|null> $values Keyed by constant name; null when undefined
     */
    public function __construct(
        private array $values,
    ) {}

    public function run(): array
    {
        $missing = [];
        $placeholder = [];
        $short = [];
        $defined = [];

        foreach (Salts::NAMES as $name) {
            $value = $this->values[$name] ?? null;

            if (!is_string($value) || trim($value) === '') {
                $missing[] = $name;

                continue;
            }

            if (self::isPlaceholder($value)) {
                $placeholder[] = $name;

                continue;
            }

            if (strlen($value) < self::MINIMUM_LENGTH) {
                $short[] = $name;

                continue;
            }

            $defined[$name] = $value;
        }

        // Names rather than values, and grouped so the report says *which* keys collide
        // rather than only that some do — the fix is per name.
        $repeated = self::repeatedNames($defined);

        $details = [
            'expected' => count(Salts::NAMES),
            'usable' => count($defined) - count($repeated),
            'missing' => $missing,
            'placeholder' => $placeholder,
            'too_short' => $short,
            'repeated' => $repeated,
        ];

        $problems = [];

        foreach ([
            'missing' => $missing,
            'a generated placeholder' => $placeholder,
            'too short to be a key' => $short,
            'repeated' => $repeated,
        ] as $label => $names) {
            if ($names === []) {
                continue;
            }

            $problems[] = sprintf('%d %s', count($names), $label);
        }

        if ($problems !== []) {
            return [VerificationResult::fail(
                self::NAME,
                sprintf('WordPress keys and salts: %s. Regenerate with `wp foehn salts:generate --force`.', implode(
                    ', ',
                    $problems,
                )),
                $details,
            )];
        }

        return [VerificationResult::pass(
            self::NAME,
            'All eight WordPress keys and salts are set, unique, and not placeholders.',
            $details,
        )];
    }

    private static function isPlaceholder(string $value): bool
    {
        return str_starts_with($value, Salts::PLACEHOLDER_PREFIX) || str_starts_with($value, Salts::INSECURE_PREFIX);
    }

    /**
     * The names sharing a value with another name, sorted so the report is deterministic.
     *
     * @param array<string, string> $defined
     * @return list<string>
     */
    private static function repeatedNames(array $defined): array
    {
        $counts = array_count_values($defined);
        $repeated = array_keys(array_filter($defined, static fn(string $value): bool => ($counts[$value] ?? 0) > 1));

        sort($repeated);

        return $repeated;
    }
}
