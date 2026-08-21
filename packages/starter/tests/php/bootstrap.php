<?php

declare(strict_types=1);

/**
 * Amorçage de la suite.
 *
 * L'autoloader ne se trouve pas au même endroit selon la disposition : à la
 * racine du paquet dans un projet créé depuis ce starter, à la racine du dépôt
 * dans ce monorepo, qui installe une seule fois pour tous les paquets. Compter
 * les niveaux ne peut être juste que pour l'une des deux — c'était le défaut
 * corrigé ici — donc on remonte jusqu'à le trouver.
 */
$autoload = null;

for ($dossier = __DIR__; $dossier !== dirname($dossier); $dossier = dirname($dossier)) {
    if (is_file($dossier . '/vendor/autoload.php')) {
        $autoload = $dossier . '/vendor/autoload.php';

        break;
    }
}

if ($autoload === null) {
    fwrite(STDERR, "vendor/autoload.php introuvable : lancer composer install.\n");

    exit(1);
}

require_once $autoload;

// Les stubs des fonctions WordPress sont livrés avec studiometa/foehn.
require_once dirname($autoload) . '/studiometa/foehn/tests/wp-stubs.php';
