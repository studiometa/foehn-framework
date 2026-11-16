<?php

declare(strict_types=1);

/**
 * Les stubs WordPress propres au projet.
 *
 * Chargé avant ceux de studiometa/foehn (vendor/studiometa/foehn/tests/wp-stubs.php),
 * qui ne définissent une fonction que si elle n'existe pas encore. Une fonction
 * déclarée ici remplace donc celle de Føhn, aujourd'hui comme après une mise à jour
 * qui en ajoute une du même nom :
 *
 *     function get_posts(array $args = []): array
 *     {
 *         return $GLOBALS['test_posts'] ?? [];
 *     }
 */
