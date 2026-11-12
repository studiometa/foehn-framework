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

        foreach (['composer.local.json', 'composer.local.lock', '.ddev/config.monorepo.yaml'] as $fichier) {
            // Les motifs sont ancrés à la racine du paquet, d'où le `/` initial.
            expect($attributs)->toContain('/' . $fichier . ' export-ignore');
        }
    });

    // La suite smoke doit tourner dans un projet créé depuis ce starter, pas
    // seulement ici. Elle a été livrée longtemps sans pouvoir démarrer : ni Pest,
    // ni mapping PSR-4 pour ses classes de support, et un bootstrap qui remontait
    // de quatre niveaux — juste depuis `packages/starter/tests/smoke/`, hors du
    // projet partout ailleurs. Un projet réel l'a portée cassée depuis le jour de
    // son initialisation sans que rien ne le signale.
    it('livre de quoi lancer ses deux suites', function () use ($racine) {
        $composer = json_decode((string) file_get_contents($racine . '/composer.json'), true);

        expect($composer['require-dev'] ?? [])->toHaveKey('pestphp/pest');
        expect($composer['autoload-dev']['psr-4'] ?? [])->toHaveKey('Studiometa\\Foehn\\Smoke\\');
        expect($composer['scripts'] ?? [])->toHaveKey('test')->toHaveKey('test:smoke');
        // Sans cette autorisation, `composer install` s'arrête sur le plugin de Pest.
        expect($composer['config']['allow-plugins'] ?? [])->toHaveKey('pestphp/pest-plugin');
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

    // Le fichier sert deux dispositions : quatre niveaux ici, deux dans un projet.
    // Un nombre en dur ne peut pas servir les deux, et c'est le défaut d'origine.
    it('trouve son autoloader dans les deux dispositions', function () use ($racine) {
        $bootstrap = (string) file_get_contents($racine . '/tests/smoke/bootstrap.php');

        expect($bootstrap)->toContain('dirname(__DIR__, 4)')->toContain('dirname(__DIR__, 2)');
        // Le monorepo reconnu à son nom : un projet installé dans un autre projet
        // PHP aurait aussi un vendor/ quatre niveaux plus haut.
        expect($bootstrap)->toContain('studiometa/foehn-monorepo');
    });

    // Ce que l'archive doit contenir pour que la suite soit lançable chez le
    // projet : les fichiers Pest comme le shell.
    it('n\'exclut pas la suite smoke de l\'archive', function () use ($racine) {
        $attributs = (string) file_get_contents($racine . '/.gitattributes');

        expect($attributs)->not->toContain('/tests/smoke');
        expect($attributs)->not->toContain('/phpunit.smoke.xml');
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
            ->toContain('vendor/studiometa/foehn/skills/foehn/SKILL.md')
            ->toContain('node_modules/@studiometa/foehn-vite-plugin/skills');
        expect((string) file_get_contents($racine . '/CLAUDE.md'))->toBe("@AGENTS.md\n");
    });

    // Les hooks du monorepo supposent des dépôts `path` absents d'un projet.
    it('ne livre pas les hooks ddev du monorepo', function () use ($racine) {
        expect((string) file_get_contents($racine . '/.ddev/config.yaml'))->not->toContain('hooks:');
    });
});
