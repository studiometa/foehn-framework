<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn skill`, against a real WordPress.
 *
 * The unit suite proves the catalog against a project built in a temporary
 * directory. What only a real site can show is that the command is registered and
 * finds the skill that the installed studiometa/foehn ships in vendor/.
 */

/**
 * Run `wp foehn skill --format=json` and decode the list it printed.
 *
 * Site::run() merges stderr, where this image prints PHP deprecations, so the JSON is
 * cut from the line that is `[` alone to the last line that is `]` alone.
 *
 * @return array{status: int, output: string, skills: mixed}
 */
function skillsOnSite(): array
{
    $result = Site::run('wp foehn skill --format=json');
    $lines = explode("\n", $result['output']);
    $start = array_search('[', $lines, true);
    $ends = array_keys($lines, ']', true);
    $end = $ends === [] ? false : max($ends);

    $skills =
        $start === false || $end === false
            ? null
            : json_decode(implode("\n", array_slice($lines, $start, $end - $start + 1)), true);

    return ['status' => $result['status'], 'output' => $result['output'], 'skills' => $skills];
}

beforeEach(
    fn() => Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.'),
);

it('lists the foehn skill of the installed framework as JSON', function () {
    $result = skillsOnSite();

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['skills'])->toBeArray($result['output']);

    $foehn = array_values(array_filter($result['skills'], static fn(array $skill): bool => $skill['name'] === 'foehn'));

    expect($foehn)->toHaveCount(1);
    expect($foehn[0]['package'])->toBe('studiometa/foehn');
    expect($foehn[0]['path'])->toBe('vendor/studiometa/foehn/skills/foehn/SKILL.md');
});

it('prints the foehn skill and one of its references', function () {
    $skill = Site::run('wp foehn skill foehn');
    $reference = Site::run('wp foehn skill foehn --reference=operations');

    expect($skill['status'])->toBe(0, $skill['output']);
    expect($skill['output'])->toContain('name: foehn')->toContain('wp foehn inspect');
    expect($reference['status'])->toBe(0, $reference['output']);
    expect($reference['output'])->toContain('wp foehn inspect');
});

it('refuses an unknown skill and names the valid ones', function () {
    $result = Site::run('wp foehn skill widgets');

    expect($result['status'])->not->toBe(0);
    expect($result['output'])->toContain("Unknown skill 'widgets'")->toContain('foehn');
});
