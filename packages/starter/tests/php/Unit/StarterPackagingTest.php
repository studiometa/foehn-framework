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

    it('exclut de l\'archive les fichiers propres au monorepo', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');

        foreach (['.ddev/config.monorepo.yaml'] as $fichier) {
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
        expect($attributs)->toContain('/tests/framework-smoke export-ignore');
        expect($racine . '/tests/smoke/run.sh')->toBeFile();
        expect($racine . '/tests/smoke/SiteTest.php')->toBeFile();
        expect($composer['autoload-dev']['psr-4'] ?? [])->not->toHaveKey('Studiometa\\Foehn\\Smoke\\');

        foreach (glob($racine . '/tests/smoke/*') ?: [] as $fichier) {
            expect((string) file_get_contents($fichier))->not->toContain('Studiometa\\Foehn\\Smoke');
        }
    });

    // Un agent de code lit AGENTS.md (Claude Code lit CLAUDE.md, qui l'importe)
    // dans le projet créé : le fichier doit partir dans l'archive et renvoyer
    // aux skills installés et à `wp foehn inspect`.
    it('livre les instructions pour les agents de code', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');
        $agents = (string) file_get_contents($racine . '/AGENTS.md');

        expect($attributs)->not->toContain('AGENTS.md')->not->toContain('CLAUDE.md');
        expect($agents)
            ->toContain('wp foehn skill')
            ->toContain('wp foehn inspect')
            ->toContain('wp foehn make:seeder')
            ->toContain('wp foehn seed')
            ->toContain('vendor/studiometa/foehn/skills/foehn/SKILL.md')
            ->toContain('node_modules/@studiometa/foehn-vite-plugin/skills');
        expect((string) file_get_contents($racine . '/CLAUDE.md'))->toBe("@AGENTS.md\n");
    });

    // L'installeur copie `public/` dans la racine web. Le répertoire part vide
    // dans l'archive, pour qu'un projet sache où mettre `robots.txt` ou
    // `.well-known/` sans lire la documentation.
    it('livre un répertoire public/ vide', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');

        expect($racine . '/public/.gitkeep')->toBeFile();
        expect($attributs)->not->toContain('/public');
    });

    // Les hooks du monorepo supposent des dépôts `path` absents d'un projet.
    it('ne livre pas les hooks ddev du monorepo', function () use ($racine) {
        expect((string) file_get_contents($racine . '/.ddev/config.yaml'))->not->toContain('hooks:');
    });
});
