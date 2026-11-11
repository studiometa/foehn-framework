<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Skills\SkillCatalog;
use Studiometa\Foehn\Skills\SkillCommand;

/**
 * The command over a project with two skills: `foehn`, which has one reference file,
 * and `bare`, which has none.
 */
function skillCommand(?string $root = null): SkillCommand
{
    $root ??= testProjectRoot([
        'vendor/composer/installed.json' => json_encode([
            'packages' => [
                ['name' => 'studiometa/foehn', 'version' => '0.7.0', 'install-path' => '../studiometa/foehn'],
                ['name' => 'acme/bare', 'version' => '1.0.0', 'install-path' => '../acme/bare'],
            ],
        ]),
        'vendor/studiometa/foehn/skills/foehn/SKILL.md' => "---\nname: foehn\ndescription: 'Build WordPress themes with Føhn. Load it when code uses Føhn attributes.'\n---\n\n# Føhn\n",
        'vendor/studiometa/foehn/skills/foehn/references/blocks.md' => "# Blocks\n",
        'vendor/acme/bare/skills/bare/SKILL.md' => "---\nname: bare\ndescription: A bare skill\n---\n",
    ]);

    return new SkillCommand(new WpCli(), new SkillCatalog(new DiscoveryLocations($root . '/app')));
}

/**
 * @return list<string>
 */
function skillCommandMessages(string $call): array
{
    return array_column(array_column(wp_stub_get_calls($call), 'args'), 'message');
}

beforeEach(fn() => wp_stub_reset());

afterEach(fn() => removeTestDirectory(sys_get_temp_dir() . '/foehn-tests'));

describe('wp foehn skill', function () {
    it('is registered as foehn skill', function () {
        $attribute = new ReflectionClass(SkillCommand::class)->getAttributes(AsCliCommand::class)[0]->newInstance();

        expect($attribute->name)->toBe('skill');
    });

    it('lists the skills as a table with the first sentence of each description', function () {
        skillCommand()([], []);

        $log = implode("\n", skillCommandMessages('wp_cli_log'));

        expect($log)
            ->toContain('name')
            ->toContain('foehn  studiometa/foehn  0.7.0')
            ->toContain('Build WordPress themes with Føhn.')
            ->not
            ->toContain('Load it when')
            ->toContain('A bare skill')
            ->toContain('wp foehn skill <name>');
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });

    it('lists the skills as JSON', function () {
        skillCommand()([], ['format' => 'json']);

        $lines = skillCommandMessages('wp_cli_line');

        expect($lines)->toHaveCount(1);
        expect(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR))->toBe([
            [
                'name' => 'bare',
                'package' => 'acme/bare',
                'version' => '1.0.0',
                'description' => 'A bare skill',
                'path' => 'vendor/acme/bare/skills/bare/SKILL.md',
            ],
            [
                'name' => 'foehn',
                'package' => 'studiometa/foehn',
                'version' => '0.7.0',
                'description' => 'Build WordPress themes with Føhn. Load it when code uses Føhn attributes.',
                'path' => 'vendor/studiometa/foehn/skills/foehn/SKILL.md',
            ],
        ]);
    });

    it('says so when no package ships a skill', function () {
        skillCommand(testProjectRoot(['vendor/composer/installed.json' => '{"packages": []}']))([], []);

        expect(skillCommandMessages('wp_cli_log'))->toBe(['No installed package ships a skill.']);
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });

    it('prints SKILL.md', function () {
        skillCommand()(['foehn'], []);

        expect(skillCommandMessages('wp_cli_line'))->toBe([
            "---\nname: foehn\ndescription: 'Build WordPress themes with Føhn. Load it when code uses Føhn attributes.'\n---\n\n# Føhn\n",
        ]);
    });

    it('prints one reference file', function () {
        skillCommand()(['foehn'], ['reference' => 'blocks']);

        expect(skillCommandMessages('wp_cli_line'))->toBe(["# Blocks\n"]);
    });

    it('lists the reference files', function () {
        skillCommand()(['foehn'], ['list-references' => true]);

        expect(skillCommandMessages('wp_cli_log'))->toBe(['blocks.md']);
    });

    it('lists the reference files as JSON', function () {
        skillCommand()(['foehn'], ['list-references' => true, 'format' => 'json']);

        expect(json_decode(skillCommandMessages('wp_cli_line')[0], true))->toBe([
            ['reference' => 'blocks.md', 'path' => 'vendor/studiometa/foehn/skills/foehn/references/blocks.md'],
        ]);
    });

    it('says so when a skill has no reference files', function () {
        skillCommand()(['bare'], ['list-references' => true]);

        expect(skillCommandMessages('wp_cli_log'))->toBe(["The skill 'bare' has no reference files."]);
    });

    it('refuses an unknown skill and names the valid ones', function () {
        skillCommand()(['nope'], []);

        expect(skillCommandMessages('wp_cli_error'))->toBe(["Unknown skill 'nope'. Available skills: bare, foehn."]);
        expect(wp_stub_get_calls('wp_cli_line'))->toBe([]);
    });

    it('refuses an unknown reference and names the valid ones', function () {
        skillCommand()(['foehn'], ['reference' => 'nope']);

        expect(skillCommandMessages('wp_cli_error'))->toBe([
            "Unknown reference 'nope' in skill 'foehn'. Available references: blocks.md.",
        ]);
    });

    it('refuses a reference of a skill that has none', function () {
        skillCommand()(['bare'], ['reference' => 'blocks']);

        expect(skillCommandMessages('wp_cli_error'))->toBe([
            "Unknown reference 'blocks' in skill 'bare'. This skill has no reference files.",
        ]);
    });

    it('refuses an unknown format', function () {
        skillCommand()([], ['format' => 'yaml']);

        expect(skillCommandMessages('wp_cli_error'))->toBe(["Unknown format 'yaml'. Use table or json."]);
    });
});
