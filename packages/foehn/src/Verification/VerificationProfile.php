<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

/**
 * The release gates `wp foehn verify` can run.
 *
 * One case per gate, and a gate exists here only once every check it promises is
 * implemented. A profile that ran a subset of its checks would report a pass that
 * means less than the name on it — which is the one failure mode a release gate
 * cannot have. {@see VerificationProfile::PLANNED} names the gates that are
 * specified but not built, so the command can say so instead of reporting an
 * unknown option.
 */
enum VerificationProfile: string
{
    /**
     * CI's gate after a WordPress core or plugin update: the PHP and WordPress
     * diagnostics raised inside one WP-CLI process.
     */
    case Updates = 'updates';

    /**
     * The deployment script's gate: the booted site is a safe production installation.
     *
     * It exists only now that every check the specification lists is implemented —
     * environment, debug, indexing, salts, real cron, heartbeat, backlog and page-cache
     * storage. A profile that shipped with some of them would report a pass that means
     * less than the name on it, which is the one failure mode a release gate cannot
     * have.
     */
    case Production = 'production';

    /**
     * Specified profiles that do not exist yet, and the roadmap item that brings each one.
     *
     * Empty, and kept rather than deleted: the next specified-but-unbuilt profile gets
     * the same "not available yet" message instead of being reported as an unknown
     * option, which is the difference between a gate that is coming and a typo.
     *
     * @var array<string, string>
     */
    public const PLANNED = [];

    /**
     * Every profile that can be selected today, for an option's help and its errors.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn(self $profile): string => $profile->value, self::cases());
    }
}
