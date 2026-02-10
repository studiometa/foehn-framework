<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Security;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Replace detailed login error messages with a generic one.
 *
 * By default WordPress reveals whether the username or the password was wrong,
 * which helps attackers enumerate valid accounts. This class returns a single
 * generic message for all login failures.
 *
 * The message is translatable via the `foehn` text domain and can be
 * overridden with a standard gettext filter.
 */
final class GenericLoginErrors
{
    #[AsFilter('login_errors')]
    public function genericLoginError(): string
    {
        return __('The credentials you entered are incorrect.', 'foehn');
    }
}
