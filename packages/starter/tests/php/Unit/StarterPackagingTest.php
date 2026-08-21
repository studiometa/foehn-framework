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

    // Les hooks du monorepo supposent des dépôts `path` absents d'un projet.
    it('ne livre pas les hooks ddev du monorepo', function () use ($racine) {
        expect((string) file_get_contents($racine . '/.ddev/config.yaml'))->not->toContain('hooks:');
    });
});
