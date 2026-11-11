<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Skills\Frontmatter;
use Studiometa\Foehn\Skills\Skill;
use Studiometa\Foehn\Skills\SkillCatalog;

/**
 * A project with three Composer packages that ship skills, one that ships none,
 * and the Vite plugin in node_modules.
 *
 * @return array<string, string>
 */
function skillProjectFiles(): array
{
    return [
        'vendor/composer/installed.json' => json_encode([
            'packages' => [
                ['name' => 'studiometa/foehn', 'version' => '0.7.0', 'install-path' => '../studiometa/foehn'],
                ['name' => 'acme/widgets', 'version' => '1.2.0', 'install-path' => '../acme/widgets'],
                ['name' => 'acme/no-skills', 'version' => '2.0.0', 'install-path' => '../acme/no-skills'],
                ['name' => 'acme/unnamed', 'version' => 'dev-main', 'install-path' => '../acme/unnamed'],
            ],
        ]),
        'vendor/studiometa/foehn/skills/foehn/SKILL.md' => <<<'MD'
            ---
            name: foehn
            description: 'Build WordPress themes with Føhn. Load it for the project''s attributes.'
            ---

            # Føhn fixture skill
            MD,
        'vendor/studiometa/foehn/skills/foehn/references/blocks.md' => "# Blocks reference\n",
        'vendor/acme/widgets/skills/widgets/SKILL.md' => <<<'MD'
            ---
            name: acme-widgets
            description: >-
              Build widgets with acme/widgets.
              Load it when code uses Acme\Widgets.
            metadata:
              author: Acme
            ---

            # Acme widgets
            MD,
        'vendor/acme/widgets/skills/widgets/references/setup.md' => "# Setup\n",
        'vendor/acme/widgets/skills/widgets/references/nested/deep.md' => "# Deep\n",
        'vendor/acme/no-skills/README.md' => "# No skills\n",
        'vendor/acme/unnamed/skills/unnamed/SKILL.md' => "# A skill without frontmatter\n",
        'node_modules/@studiometa/foehn-vite-plugin/package.json' => '{"version": "0.7.1"}',
        'node_modules/@studiometa/foehn-vite-plugin/skills/foehn-vite-plugin/SKILL.md' => <<<'MD'
            ---
            name: foehn-vite-plugin
            description: "Configure the \"Vite\" build of a Føhn theme."
            ---

            # Vite plugin fixture skill
            MD,
    ];
}

function skillCatalog(string $root): SkillCatalog
{
    return new SkillCatalog(new DiscoveryLocations($root . '/app'));
}

afterEach(fn() => removeTestDirectory(sys_get_temp_dir() . '/foehn-tests'));

describe('SkillCatalog', function () {
    it('lists the skills of every installed package, then the Vite plugin', function () {
        $skills = skillCatalog(testProjectRoot(skillProjectFiles()))->all();

        expect(array_map(static fn(Skill $skill): array => $skill->toArray(), $skills))->toBe([
            [
                'name' => 'unnamed',
                'package' => 'acme/unnamed',
                'version' => 'dev-main',
                'description' => '',
                'path' => 'vendor/acme/unnamed/skills/unnamed/SKILL.md',
            ],
            [
                'name' => 'acme-widgets',
                'package' => 'acme/widgets',
                'version' => '1.2.0',
                'description' => 'Build widgets with acme/widgets. Load it when code uses Acme\\Widgets.',
                'path' => 'vendor/acme/widgets/skills/widgets/SKILL.md',
            ],
            [
                'name' => 'foehn',
                'package' => 'studiometa/foehn',
                'version' => '0.7.0',
                'description' => "Build WordPress themes with Føhn. Load it for the project's attributes.",
                'path' => 'vendor/studiometa/foehn/skills/foehn/SKILL.md',
            ],
            [
                'name' => 'foehn-vite-plugin',
                'package' => '@studiometa/foehn-vite-plugin',
                'version' => '0.7.1',
                'description' => 'Configure the "Vite" build of a Føhn theme.',
                'path' => 'node_modules/@studiometa/foehn-vite-plugin/skills/foehn-vite-plugin/SKILL.md',
            ],
        ]);
    });

    it('finds a skill by its name and reads its files', function () {
        $skill = skillCatalog(testProjectRoot(skillProjectFiles()))->find('acme-widgets');

        expect($skill)->not->toBeNull();
        expect($skill->contents())->toContain('# Acme widgets');
        expect($skill->references())->toBe(['nested/deep.md', 'setup.md']);
        expect($skill->reference('setup.md'))->toBe("# Setup\n");
        expect($skill->reference('setup'))->toBe("# Setup\n");
        expect($skill->reference('references/nested/deep.md'))->toBe("# Deep\n");
    });

    it('returns null for an unknown skill or reference', function () {
        $catalog = skillCatalog(testProjectRoot(skillProjectFiles()));

        expect($catalog->find('nope'))->toBeNull();
        expect($catalog->find('foehn')?->reference('nope'))->toBeNull();
        expect($catalog->find('unnamed')?->references())->toBe([]);
    });

    it('never reads a file outside the references directory', function () {
        $skill = skillCatalog(testProjectRoot(skillProjectFiles()))->find('foehn');

        expect($skill?->reference('../SKILL.md'))->toBeNull();
        expect($skill?->reference('../../../../composer/installed.json'))->toBeNull();
    });

    it('keeps the vendor path of a symlinked path repository', function () {
        $root = testProjectRoot([
            'vendor/composer/installed.json' => json_encode([
                'packages' => [
                    ['name' => 'studiometa/foehn', 'version' => 'dev-main', 'install-path' => '../studiometa/foehn'],
                ],
            ]),
            'packages/foehn/skills/foehn/SKILL.md' => "---\nname: foehn\n---\n",
        ]);
        mkdir($root . '/vendor/studiometa', 0o777, true);
        symlink($root . '/packages/foehn', $root . '/vendor/studiometa/foehn');

        expect(skillCatalog($root)->find('foehn')?->path)->toBe('vendor/studiometa/foehn/skills/foehn');
    });

    it('reads a package installed above the project root', function () {
        $root = testProjectRoot([
            'vendor/composer/installed.json' => json_encode([
                'packages' => [
                    ['name' => 'acme/outside', 'version' => '1.0.0', 'install-path' => '../../../outside'],
                ],
            ]),
        ]);
        $outside = dirname($root) . '/outside/skills/outside';
        mkdir($outside, 0o777, true);
        file_put_contents($outside . '/SKILL.md', "---\nname: outside\n---\n");

        $skill = skillCatalog($root)->find('outside');

        expect($skill?->path)->toBe('../outside/skills/outside');
        expect($skill?->contents())->toContain('name: outside');
    });

    it('finds nothing outside a Composer project', function () {
        expect(skillCatalog(testAppPath())->all())->toBe([]);
    });

    it('finds nothing when installed.json cannot be read', function () {
        $root = testProjectRoot(['vendor/composer/installed.json' => 'not json']);

        expect(skillCatalog($root)->all())->toBe([]);
    });
});

describe('Frontmatter', function () {
    it('reads plain, quoted and block values', function () {
        expect(Frontmatter::parse(<<<'MD'
            ---
            name: plain value
            single: 'it''s'
            double: "a \"b\""
            literal: |
              one
              two
            nested:
              key: skipped
            ---
            body
            MD))->toBe([
            'name' => 'plain value',
            'single' => "it's",
            'double' => 'a "b"',
            'literal' => "one\ntwo",
            'nested' => '',
        ]);
    });

    it('reads nothing without a frontmatter block', function () {
        expect(Frontmatter::parse("# Title\n\nname: not a field\n"))->toBe([]);
    });
});
