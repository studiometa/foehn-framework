<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

/**
 * A named replacement for one column of an exported row.
 *
 * Named rather than given as a closure, so a rule can sit in a config file and be
 * compared in a test. Every case is deterministic: the same value (or the same seed)
 * always gives the same result, so two exports of one database match, and nothing of
 * the original value is left in the result.
 */
enum Anonymizer: string
{
    /** The domain of every address this writes. `.invalid` is reserved and never delivers. */
    public const string DOMAIN = 'example.invalid';

    /** An address in the TEST-NET-1 range, which is reserved for documentation. */
    public const string IP = '192.0.2.1';

    /** `user-<token>@example.invalid`. */
    case Email = 'email';

    /** `user-<token>`, for a login or a nicename. */
    case Username = 'username';

    /** `User <token>`, for a display name. */
    case Name = 'name';

    /** A reserved documentation address. */
    case Ip = 'ip';

    /** An empty string, for secrets and for values nothing should replace. */
    case Empty = 'empty';

    /**
     * The replacement for a value.
     *
     * The token is the seed when the rule gives one (a row ID, so `user-42` is user 42),
     * and otherwise a short hash of the value. NULL and an empty string carry nothing
     * and stay as they are.
     */
    public function apply(?string $value, ?string $seed = null): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $token = $seed ?? substr(hash('sha256', $value), 0, 12);

        return match ($this) {
            self::Email => sprintf('user-%s@%s', $token, self::DOMAIN),
            self::Username => 'user-' . $token,
            self::Name => 'User ' . $token,
            self::Ip => self::IP,
            self::Empty => '',
        };
    }

    /**
     * Whether this replaces who a row is about, rather than a secret.
     *
     * A row whose email is in an allowed domain keeps these columns. It never keeps a
     * secret: a password hash is emptied for the team as for everyone else.
     */
    public function isIdentity(): bool
    {
        return match ($this) {
            self::Email, self::Username, self::Name => true,
            self::Ip, self::Empty => false,
        };
    }
}
