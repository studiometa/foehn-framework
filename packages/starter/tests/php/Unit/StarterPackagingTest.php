<?php

declare(strict_types=1);

/**
 * Ce que l'archive Composer doit livrer, et ce qu'elle ne doit pas.
 *
 * Ces trois défauts ont été livrés parce que rien ne regardait le paquet du
 * point de vue d'un projet installé : la CI ne lint pas le starter et n'exécute
 * pas sa suite.
 */
describe('starter packaging', function () {
    // tests/php/Unit -> racine du paquet.
    $racine = dirname(__DIR__, 3);

    // `post-create-project-cmd` recopiait `.env.example` par-dessus le `.env`
    // que l'installeur venait de remplir, effaçant les huit clés de sécurité.
    it('ne recopie pas .env.example par-dessus le .env généré', function () use ($racine) {
        $composer = json_decode((string) file_get_contents($racine . '/composer.json'), true);
        $scripts = $composer['scripts'] ?? [];

        expect($scripts)->not->toHaveKey('post-create-project-cmd');
    });

    // Les chemins vers le monorepo empêchaient la suite et oxlint de démarrer.
    it('ne référence rien hors du projet', function () use ($racine) {
        $bootstrap = (string) file_get_contents($racine . '/tests/php/bootstrap.php');
        $oxlint = (string) file_get_contents($racine . '/.oxlintrc.json');

        expect($bootstrap)->not->toContain('dirname(__DIR__, 3)');
        expect($bootstrap)->not->toContain('dirname(__DIR__, 4)');
        expect($oxlint)->not->toContain('../../node_modules');
    });

    // Les stubs de Føhn ne définissent une fonction que si elle n'existe pas
    // encore. Chargés avant ceux du projet, chaque nouvelle version de Føhn qui
    // ajoutait un stub du même nom remplaçait celui du projet sans rien dire.
    it('charge les stubs du projet avant ceux de Føhn', function () use ($racine) {
        $bootstrap = (string) file_get_contents($racine . '/tests/php/bootstrap.php');
        $projet = strpos($bootstrap, "require_once __DIR__ . '/stubs.php';");
        $foehn = strpos($bootstrap, '/studiometa/foehn/tests/wp-stubs.php');

        expect($projet)->toBeInt();
        expect($foehn)->toBeInt();
        expect($projet)->toBeLessThan($foehn);
        expect(is_file($racine . '/tests/php/stubs.php'))->toBeTrue();
    });

    it('exclut de l\'archive les fichiers propres au monorepo', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');

        foreach (['.gitattributes', 'tests/framework-smoke', 'tests/php/Unit/StarterPackagingTest.php'] as $fichier) {
            // Les motifs sont ancrés à la racine du paquet, d'où le `/` initial.
            expect($attributs)->toContain('/' . $fichier . ' export-ignore');
        }
    });

    // La suite smoke du projet doit tourner dans un projet créé depuis ce
    // starter, pas seulement ici. Elle a été livrée longtemps sans pouvoir
    // démarrer : ni Pest, ni autoloader joignable depuis le projet.
    it('livre de quoi lancer ses deux suites', function () use ($racine) {
        $composer = json_decode((string) file_get_contents($racine . '/composer.json'), true);
        $smoke = (string) file_get_contents($racine . '/phpunit.smoke.xml');

        expect($composer['require-dev'] ?? [])->toHaveKey('pestphp/pest');
        expect($composer['scripts'] ?? [])->toHaveKey('test')->toHaveKey('test:smoke');
        // Sans cette autorisation, `composer install` s'arrête sur le plugin de Pest.
        expect($composer['config']['allow-plugins'] ?? [])->toHaveKey('pestphp/pest-plugin');
        expect($smoke)->toContain('bootstrap="vendor/autoload.php"');
    });

    // Une version exacte, pas une plage : `composer create-project` doit installer
    // une combinaison que quelqu'un a fait tourner, pas ce que la plage admet ce
    // jour-là. Une plage remise à la place d'un pin ne casserait rien tout de
    // suite — elle rendrait juste le pin sans objet, sans bruit.
    it('épingle le framework à une version exacte', function () use ($racine) {
        $composer = json_decode((string) file_get_contents($racine . '/composer.json'), true);

        foreach (['studiometa/foehn', 'studiometa/foehn-installer'] as $paquet) {
            $contrainte = $composer['require'][$paquet] ?? '';

            expect($contrainte)->toMatch('/^\\d+\\.\\d+\\.\\d+$/');
        }
    });

    // Le projet garde tests/smoke/ : run.sh et un test court qu'il peut
    // compléter. La suite du framework teste Føhn, pas le projet : elle reste
    // dans le monorepo, avec ses classes de support.
    it('livre la suite smoke du projet et pas celle du framework', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');
        $composer = json_decode((string) file_get_contents($racine . '/composer.json'), true);

        expect($attributs)->not->toContain('/tests/smoke')->not->toContain('/phpunit.smoke.xml');
        expect($racine . '/tests/smoke/run.sh')->toBeFile();
        expect($racine . '/tests/smoke/SiteTest.php')->toBeFile();
        expect($composer['autoload-dev']['psr-4'] ?? [])->not->toHaveKey('Studiometa\\Foehn\\Smoke\\');

        foreach (glob($racine . '/tests/smoke/*') ?: [] as $fichier) {
            expect((string) file_get_contents($fichier))->not->toContain('Studiometa\\Foehn\\Smoke');
        }
    });

    // Un agent de code (Claude Code, Codex…) lit AGENTS.md
    // dans le projet créé : le fichier doit partir dans l'archive et renvoyer
    // aux skills installés et à `wp foehn inspect`.
    it('livre les instructions pour les agents de code', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');
        $agents = (string) file_get_contents($racine . '/AGENTS.md');

        expect($attributs)->not->toContain('AGENTS.md');
        expect($agents)
            ->toContain('wp foehn skill')
            ->toContain('wp foehn inspect')
            ->toContain('wp foehn make:seeder')
            ->toContain('wp foehn seed')
            ->toContain('vendor/studiometa/foehn/skills/foehn/SKILL.md')
            ->toContain('node_modules/@studiometa/foehn-vite-plugin/skills');
        expect($racine . '/CLAUDE.md')->not->toBeFile();
    });

    // Un projet créé reçoit les mises à jour de Renovate avec les règles de Føhn :
    // celles du preset partagé, les correctifs de sécurité sans attente, et les
    // paquets Føhn ensemble, puisqu'ils portent tous la même version exacte.
    it('livre une configuration Renovate', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');
        $renovate = json_decode((string) file_get_contents($racine . '/renovate.json'), true);

        expect($attributs)->not->toContain('renovate.json');
        expect($renovate['extends'] ?? [])->toContain('github>studiometa/renovate-config');
        expect($renovate['vulnerabilityAlerts'] ?? [])->toHaveKey('minimumReleaseAge', null);

        $groupes = array_values(array_filter(
            $renovate['packageRules'] ?? [],
            static fn(array $regle): bool => ($regle['groupName'] ?? null) === 'Føhn',
        ));

        expect($groupes)->toHaveCount(1);
        expect($groupes[0]['matchPackageNames'])->toBe(['studiometa/foehn*', '@studiometa/foehn-*']);
    });

    // L'installeur copie `public/` dans la racine web. Le répertoire part vide
    // dans l'archive, pour qu'un projet sache où mettre `robots.txt` ou
    // `.well-known/` sans lire la documentation.
    it('livre un répertoire public/ vide', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');

        expect($racine . '/public/.gitkeep')->toBeFile();
        expect($attributs)->not->toContain('/public');
    });

    // `ddev start` installe WordPress et active le thème dans un projet créé :
    // la documentation le promet. Le hook ne doit rien supposer du monorepo,
    // comme des dépôts `path` vers ../foehn.
    it('installe WordPress au démarrage de ddev sans supposer le monorepo', function () use ($racine) {
        $ddev = (string) file_get_contents($racine . '/.ddev/config.yaml');

        expect($ddev)
            ->toContain('post-start:')
            ->toContain('wp core is-installed')
            ->toContain('wp theme activate')
            ->not->toContain('../');
        expect($racine . '/.ddev/config.monorepo.yaml')->not->toBeFile();
    });
});
