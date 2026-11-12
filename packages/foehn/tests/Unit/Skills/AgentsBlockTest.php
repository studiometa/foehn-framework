<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Skills\AgentsBlock;
use Studiometa\Foehn\Skills\SkillCatalog;

/**
 * A project with the `foehn` skill and two references: one with a summary paragraph
 * that holds a link, one with a title only.
 *
 * @param array<string, string> $extra
 */
function agentsBlockProject(array $extra = []): string
{
    return testProjectRoot([
        'vendor/composer/installed.json' => json_encode([
            'packages' => [
                ['name' => 'studiometa/foehn', 'version' => '0.7.0', 'install-path' => '../studiometa/foehn'],
            ],
        ]),
        'vendor/studiometa/foehn/skills/foehn/SKILL.md' => "---\nname: foehn\ndescription: 'Build WordPress themes with Føhn. Load it when code uses Føhn attributes.'\n---\n\n# Føhn\n",
        'vendor/studiometa/foehn/skills/foehn/references/blocks.md' => "# Blocks\n\nNative blocks and [block patterns](https://example.com/patterns.md). More text.\n\n## The model\n",
        'vendor/studiometa/foehn/skills/foehn/references/views.md' => "# Views\n\n## Request flow\n",
        ...$extra,
    ]);
}

function agentsBlock(string $root): AgentsBlock
{
    return new AgentsBlock(new SkillCatalog(new DiscoveryLocations($root . '/app')));
}

afterEach(fn() => removeTestDirectory(sys_get_temp_dir() . '/foehn-tests'));

describe('AgentsBlock::render()', function () {
    it('starts and ends with the markers', function () {
        $block = agentsBlock(agentsBlockProject())->render();

        expect($block)->toStartWith(AgentsBlock::BEGIN . "\n")->toEndWith("\n\n" . AgentsBlock::END);
    });

    it('gives the directive', function () {
        $block = agentsBlock(agentsBlockProject())->render();

        expect($block)
            ->toContain('wp foehn inspect --format=json')
            ->toContain('--dry-run')
            ->toContain('wp foehn seed')
            ->toContain('wp foehn errors')
            ->toContain('wp foehn trace <path>')
            ->toContain('wp foehn log');
    });

    it('gives one line for each skill and each reference, with the command that prints it', function () {
        $block = agentsBlock(agentsBlockProject())->render();

        expect($block)
            ->toContain(
                "\n- `wp foehn skill foehn` (`vendor/studiometa/foehn/skills/foehn/SKILL.md`): Build WordPress themes with Føhn.\n",
            )
            ->toContain("\n  - `wp foehn skill foehn --reference=blocks`: Native blocks and block patterns.\n")
            ->toContain("\n  - `wp foehn skill foehn --reference=views`: Views\n")
            ->not->toContain('Load it when')
            ->not->toContain('example.com');
    });

    it('says so when no package ships a skill', function () {
        $root = testProjectRoot(['vendor/composer/installed.json' => '{"packages": []}']);

        expect(agentsBlock($root)->render())->toContain("\nNo installed package ships a skill.\n");
    });

    it('cuts a long summary', function () {
        $root = agentsBlockProject([
            'vendor/studiometa/foehn/skills/foehn/references/long.md' =>
                '# Long' . "\n\n" . str_repeat('word ', 80) . "\n",
        ]);

        $line = array_values(array_filter(
            explode("\n", agentsBlock($root)->render()),
            static fn(string $line): bool => str_contains($line, '--reference=long'),
        ))[0];

        expect($line)->toEndWith('…');
        expect(mb_strlen($line))->toBeLessThan(260);
    });
});

describe('AgentsBlock::merge()', function () {
    $block = AgentsBlock::BEGIN . "\nnew\n" . AgentsBlock::END;

    it('makes a file of the block alone when there is no file', function () use ($block) {
        expect(AgentsBlock::merge(null, $block))->toBe($block . "\n");
        expect(AgentsBlock::merge('', $block))->toBe($block . "\n");
    });

    it('adds the block at the end of a file without markers, and keeps every byte of it', function () use ($block) {
        expect(AgentsBlock::merge("# Project\n\nOur rules.\n", $block))
            ->toBe("# Project\n\nOur rules.\n\n" . $block . "\n");
        expect(AgentsBlock::merge("# Project\n\nNo final line break", $block))
            ->toBe("# Project\n\nNo final line break\n\n" . $block . "\n");
    });

    it('replaces only what is between the markers', function () use ($block) {
        $contents =
            "# Project\n\nBefore.\n\n"
            . AgentsBlock::BEGIN
            . "\nold\nlines\n"
            . AgentsBlock::END
            . "\n\n## Ours\n\nAfter.  \n";

        expect(AgentsBlock::merge($contents, $block))
            ->toBe("# Project\n\nBefore.\n\n" . $block . "\n\n## Ours\n\nAfter.  \n");
    });

    it('changes nothing when the block is current', function () use ($block) {
        $contents = "# Project\n\n" . $block . "\n\nAfter.\n";

        expect(AgentsBlock::merge($contents, $block))->toBe($contents);
        expect(AgentsBlock::merge(AgentsBlock::merge("# Project\n", $block), $block))
            ->toBe(AgentsBlock::merge("# Project\n", $block));
    });

    it('refuses broken markers', function (string $contents) use ($block) {
        expect(fn() => AgentsBlock::merge($contents, $block))->toThrow(InvalidArgumentException::class);
    })->with([
        'a begin without an end' => AgentsBlock::BEGIN . "\nold\n",
        'an end without a begin' => "old\n" . AgentsBlock::END . "\n",
        'an end before the begin' => AgentsBlock::END . "\n" . AgentsBlock::BEGIN . "\n",
        'two blocks' => str_repeat(AgentsBlock::BEGIN . "\n" . AgentsBlock::END . "\n", 2),
    ]);
});
