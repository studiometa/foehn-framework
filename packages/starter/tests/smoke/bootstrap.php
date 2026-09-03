<?php

declare(strict_types=1);

/**
 * Bootstrap for the smoke suite.
 *
 * Deliberately *not* `tests/php/bootstrap.php`. That one loads WordPress function stubs,
 * which exist so a unit test can call `add_action()` without WordPress. This suite talks
 * HTTP to a real WordPress and reads real files, so a stub in scope would be actively
 * misleading — a test could call `home_url()` and get `http://example.com` back while
 * believing it had asked the site.
 *
 * All it needs is an autoloader that knows `Studiometa\Foehn\Smoke\`, for the support
 * classes beside it. No framework class is ever loaded: the one mention of
 * `PageCacheConfig` is inside a string this suite writes into a generated config file,
 * which PHP-FPM then reads in another process.
 *
 * **Two layouts, because this suite ships.** It lives at
 * `packages/starter/tests/smoke/` in the framework's monorepo, where the autoloader is
 * four levels up, and at `<project>/tests/smoke/` in every project created from this
 * starter, where it is two. The monorepo is recognised by name rather than by finding a
 * `vendor/` four levels up: a project installed inside another PHP project would have
 * one too, and picking it would load a stranger's autoloader.
 */
$monorepo = dirname(__DIR__, 4);
$project = dirname(__DIR__, 2);

$manifest = $monorepo . '/composer.json';
$isMonorepo = is_file($manifest) && str_contains((string) file_get_contents($manifest), '"studiometa/foehn-monorepo"');

$autoload = ($isMonorepo ? $monorepo : $project) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, sprintf(
        "Aucun autoloader trouvé pour la suite smoke : %s n'existe pas.\nLancez `composer install`.\n",
        $autoload,
    ));

    exit(1);
}

require_once $autoload;
