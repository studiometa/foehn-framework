<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn log`, against the debug log of a real WordPress.
 *
 * The unit suite proves how the command parses and groups each kind of entry. What
 * only a real site can show is that the generated wp-config.php turns the log on with
 * WP_DEBUG, that PHP and error_log() write to the file the command reads, and that the
 * command finds it. The log belongs to WordPress, so the test does not clear it: each
 * run writes a marker of its own and asks only for its entries.
 */

/**
 * The groups the command printed as JSON.
 *
 * Site::run() merges stderr, where this image prints PHP deprecations, so the JSON is
 * the last line that decodes to a list.
 *
 * @return list<array<string, mixed>>
 */
function debugLogGroups(string $options): array
{
    $lines = explode("\n", Site::run('wp foehn log --format=json ' . $options)['output']);

    foreach (array_reverse($lines) as $line) {
        $groups = json_decode(trim($line), true);

        if (is_array($groups) && array_is_list($groups)) {
            return $groups;
        }
    }

    return [];
}

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('groups a repeated warning, and reads an error_log() message', function () {
    $marker = 'Log smoke ' . bin2hex(random_bytes(4));

    Site::run(sprintf(
        "wp eval 'trigger_error(\"%1\$s\", E_USER_WARNING); trigger_error(\"%1\$s\", E_USER_WARNING); error_log(\"%1\$s message\");'",
        $marker,
    ));

    $groups = debugLogGroups(sprintf("--grep='%s'", $marker));

    expect($groups)->toHaveCount(2);
    expect($groups[0])->toMatchArray(['level' => 'log', 'message' => $marker . ' message', 'count' => 1]);
    expect($groups[1])->toMatchArray(['level' => 'warning', 'message' => $marker, 'count' => 2, 'line' => 1]);
    expect($groups[1]['file'])->toContain("eval()'d code");
});

it('attaches the stack trace of an uncaught exception', function () {
    $marker = 'Log smoke ' . bin2hex(random_bytes(4));

    Site::run(sprintf("wp eval 'throw new RuntimeException(\"%s\");'", $marker));

    $groups = debugLogGroups(sprintf("--grep='%s' --level=fatal", $marker));

    expect($groups)->toHaveCount(1);
    expect($groups[0]['message'])->toBe('Uncaught RuntimeException: ' . $marker);
    expect($groups[0]['trace'])->not->toBe([]);
});

it('prints the groups in a few lines', function () {
    $output = Site::run('wp foehn log --limit=3')['output'];

    expect($output)->not->toContain('The debug log is off');
    expect(substr_count(trim($output), "\n"))->toBeLessThan(30);
});
