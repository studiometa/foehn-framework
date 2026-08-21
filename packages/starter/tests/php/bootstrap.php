<?php

declare(strict_types=1);

// Résolus depuis la racine du projet, pas depuis une position dans le monorepo :
// un projet créé depuis ce starter n'a ni `../../foehn` ni un `vendor/` deux
// niveaux plus haut. Les stubs WordPress sont livrés avec `studiometa/foehn`.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/vendor/studiometa/foehn/tests/wp-stubs.php';
