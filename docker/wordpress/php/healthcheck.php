<?php

/**
 * The container's health endpoint.
 *
 * Served by `location = /healthcheck`, which passes the request to PHP-FPM with
 * `SCRIPT_FILENAME=/opt/healthcheck.php`. Going through PHP is the point: a
 * check answered by NGINX alone stays green while PHP is dead.
 *
 * Deliberately minimal — it says PHP-FPM answers, not that WordPress or the
 * database do. A container that starts without a database should stay reachable
 * to be diagnosed, not be killed and restarted in a loop.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "OK\n";
