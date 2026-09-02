<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Security;

use Random\RandomException;
use RuntimeException;

/**
 * WordPress's eight security keys, and the two shapes they are written in.
 *
 * These keys sign authentication cookies and nonces. A site whose keys are guessable
 * is a site whose login cookies can be forged, which is why nothing here has a
 * default: a value is either random or absent.
 *
 * They belong in the environment, which is what lets a project keep them wherever it
 * keeps its other secrets: a .env file, container environment variables, a vault.
 * wp-config.php reads each name from the environment, and still requires
 * config/wordpress-salts.config.php when a project would rather use a PHP file.
 *
 * `studiometa/foehn-installer` writes the same lines on a first install, from its own
 * copy of this — a Composer plugin cannot rely on the project's autoloader. The two
 * formats below are the contract between the two.
 */
final readonly class Salts
{
    /**
     * The constants WordPress expects, in the order it documents them.
     *
     * @var list<string>
     */
    public const NAMES = [
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    /**
     * Marks a value as one nobody should trust — a placeholder from an install that
     * never generated real keys.
     */
    public const PLACEHOLDER_PREFIX = 'change-me-';

    /**
     * What the generated `wp-config.php` defines when a key is missing outside production.
     *
     * A derivable value, deliberately: it lets a developer log in on a machine where no
     * keys were ever generated, and it is refused in production before a request is
     * served. It is listed here because production verification has to recognise it —
     * a constant that *is* defined and *is* non-empty and is still worthless is
     * otherwise indistinguishable from a real key.
     *
     * `studiometa/foehn-installer` writes the string from its own copy, the way it
     * writes the eight names above: a Composer plugin cannot reach the project's
     * autoloader. The two copies are the contract, and this is the readable half.
     */
    public const INSECURE_PREFIX = 'insecure-development-key-';

    /**
     * @param array<string, string> $values Keyed by constant name
     */
    private function __construct(
        public array $values,
    ) {}

    /**
     * Generate a fresh set.
     *
     * @throws RuntimeException If the system has no source of randomness
     */
    public static function generate(): self
    {
        $values = [];

        foreach (self::NAMES as $name) {
            try {
                // WordPress's own generator produces 64 printable characters; base64
                // of 48 bytes lands in the same range without a character set to
                // escape when the value is written into a PHP file.
                $values[$name] = base64_encode(random_bytes(48));
            } catch (RandomException $e) {
                throw new RuntimeException('Could not generate security salts: ' . $e->getMessage(), previous: $e);
            }
        }

        return new self($values);
    }

    /**
     * The lines a dotenv file holds, which is where these belong by default.
     */
    public function toEnvLines(): string
    {
        $lines = [];

        foreach ($this->values as $name => $value) {
            // Quoted: a generated value contains `+` and `/`, and may end in `=`.
            $lines[] = sprintf('%s="%s"', $name, $value);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * The PHP file wp-config.php requires, for a project that prefers one.
     */
    public function toPhpFile(): string
    {
        $lines = [
            '<?php',
            '',
            '/**',
            ' * WordPress security keys — generated, and secret.',
            ' *',
            ' * Keep this file out of version control. Regenerating it logs every user out,',
            ' * because the cookies they hold were signed with the previous keys.',
            ' *',
            ' * Regenerate with: wp foehn salts:generate --force',
            ' */',
            '',
        ];

        foreach ($this->values as $name => $value) {
            $lines[] = sprintf("define('%s', '%s');", $name, addslashes($value));
        }

        return implode("\n", $lines) . "\n";
    }
}
