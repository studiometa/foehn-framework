<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

/**
 * The rule a WP-CLI command namespace follows: the first word after `wp`.
 *
 * The project's commands go under FoehnConfig::$cliNamespace, the framework's under
 * `wp foehn`, and the commands of each other package under a namespace of its own.
 */
final class CliNamespace
{
    /**
     * The namespace of the framework's own commands.
     */
    public const string FRAMEWORK = 'foehn';

    /**
     * The Composer package whose commands go under FRAMEWORK.
     */
    public const string FRAMEWORK_PACKAGE = 'studiometa/foehn';

    /**
     * What a valid namespace is, in words, for an error message.
     */
    public const string RULE = 'one word of lowercase letters, digits, "-" and "_", other than "foehn"';

    /**
     * Whether a namespace can be given to the project or to a package.
     *
     * WP-CLI splits a command name on spaces, so an empty name or one with a space
     * registers the commands where `wp` cannot reach them. `foehn` belongs to the
     * framework: another source there would put its commands among the framework's,
     * where one with the same name replaces the other.
     */
    public static function isValid(string $namespace): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $namespace) === 1 && $namespace !== self::FRAMEWORK;
    }
}
