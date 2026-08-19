<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Security;

use Random\RandomException;
use RuntimeException;

/**
 * WordPress's eight security keys, and the file the generated wp-config.php reads
 * them from.
 *
 * These keys sign authentication cookies and nonces. A site whose keys are guessable
 * is a site whose login cookies can be forged, which is why nothing here has a
 * default: a value is either random or absent.
 *
 * `studiometa/foehn-installer` writes the same file on a first install, from its own
 * copy of this — a Composer plugin cannot rely on the project's autoloader. The file
 * format is the contract between the two, and wp-config.php only requires it.
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
     * The PHP file wp-config.php requires.
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
