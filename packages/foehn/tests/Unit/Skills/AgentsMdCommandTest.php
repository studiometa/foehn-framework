<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Skills\AgentsBlock;
use Studiometa\Foehn\Skills\AgentsMdCommand;
use Studiometa\Foehn\Skills\SkillCatalog;

/**
 * A project with one skill, and AGENTS.md when `$agents` is given.
 */
function agentsMdProject(?string $agents = null): string
{
    $files = [
        'vendor/composer/installed.json' => json_encode([
            'packages' => [
                ['name' => 'studiometa/foehn', 'version' => '0.7.0', 'install-path' => '../studiometa/foehn'],
            ],
        ]),
        'vendor/studiometa/foehn/skills/foehn/SKILL.md' => "---\nname: foehn\ndescription: Build themes.\n---\n",
        'vendor/studiometa/foehn/skills/foehn/references/blocks.md' => "# Blocks\n\nNative blocks.\n",
    ];

    if ($agents !== null) {
        $files['AGENTS.md'] = $agents;
    }

    return testProjectRoot($files);
}

/**
 * @param array<string, mixed> $assocArgs
 */
function runAgentsMd(string $root, array $assocArgs = []): void
{
    $catalog = new SkillCatalog(new DiscoveryLocations($root . '/app'));

    (new AgentsMdCommand(new WpCli(), new DiscoveryLocations($root . '/app'), new AgentsBlock($catalog)))(
        [],
        $assocArgs,
    );
}

function agentsMdBlock(string $root): string
{
    return new AgentsBlock(new SkillCatalog(new DiscoveryLocations($root . '/app')))->render();
}

/**
 * @return list<string>
 */
function agentsMdMessages(string $call): array
{
    return array_column(array_column(wp_stub_get_calls($call), 'args'), 'message');
}

beforeEach(fn() => wp_stub_reset());

afterEach(fn() => removeTestDirectory(sys_get_temp_dir() . '/foehn-tests'));

describe('wp foehn agents-md', function () {
    it('is registered as foehn agents-md, a command that writes', function () {
        $attribute = new ReflectionClass(AgentsMdCommand::class)->getAttributes(AsCliCommand::class)[0]->newInstance();

        expect($attribute->name)->toBe('agents-md');
        expect($attribute->writes)->toBeTrue();
    });

    it('creates AGENTS.md with the block when the file is missing', function () {
        $root = agentsMdProject();

        runAgentsMd($root);

        expect(file_get_contents($root . '/AGENTS.md'))->toBe(agentsMdBlock($root) . "\n");
        expect(agentsMdMessages('wp_cli_success'))->toBe(['Created AGENTS.md with the Føhn block.']);
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });

    it('adds the block at the end when AGENTS.md has no markers', function () {
        $root = agentsMdProject("# Our project\n\nOur rules.\n");

        runAgentsMd($root);

        expect(file_get_contents($root . '/AGENTS.md'))
            ->toBe("# Our project\n\nOur rules.\n\n" . agentsMdBlock($root) . "\n");
        expect(agentsMdMessages('wp_cli_success'))->toBe(['Added the Føhn block at the end of AGENTS.md.']);
    });

    it('refreshes the block and leaves the text around it alone', function () {
        $before = "# Our project\n\nRead this first.\n\n";
        $after = "\n\n## Conventions\n\n- Ours, edited by hand.\n";
        $root = agentsMdProject($before . AgentsBlock::BEGIN . "\nan older block\n" . AgentsBlock::END . $after);

        runAgentsMd($root);

        expect(file_get_contents($root . '/AGENTS.md'))->toBe($before . agentsMdBlock($root) . $after);
        expect(agentsMdMessages('wp_cli_success'))->toBe(['Updated the Føhn block in AGENTS.md.']);
    });

    it('writes nothing on a second run', function () {
        $root = agentsMdProject("# Our project\n");

        runAgentsMd($root);
        $first = file_get_contents($root . '/AGENTS.md');
        touch($root . '/AGENTS.md', 1_000_000_000);
        clearstatcache();
        wp_stub_reset();

        runAgentsMd($root);
        clearstatcache();

        expect(file_get_contents($root . '/AGENTS.md'))->toBe($first);
        expect(filemtime($root . '/AGENTS.md'))->toBe(1_000_000_000);
        expect(agentsMdMessages('wp_cli_success'))->toBe(['The Føhn block in AGENTS.md is up to date.']);
    });

    it('follows a skill that was installed since the last run', function () {
        $root = agentsMdProject();

        runAgentsMd($root);
        file_put_contents(
            $root . '/vendor/studiometa/foehn/skills/foehn/references/views.md',
            "# Views\n\nTemplate controllers.\n",
        );
        wp_stub_reset();

        runAgentsMd($root);

        expect(file_get_contents($root . '/AGENTS.md'))->toContain('--reference=views`: Template controllers.');
        expect(agentsMdMessages('wp_cli_success'))->toBe(['Updated the Føhn block in AGENTS.md.']);
    });

    it('refuses broken markers and leaves the file as it was', function () {
        $contents = "# Our project\n\n" . AgentsBlock::BEGIN . "\nthe end marker was deleted\n";
        $root = agentsMdProject($contents);

        runAgentsMd($root);

        expect(file_get_contents($root . '/AGENTS.md'))->toBe($contents);
        expect(agentsMdMessages('wp_cli_error'))->toHaveCount(1);
        expect(agentsMdMessages('wp_cli_error')[0])
            ->toContain('has broken Føhn markers, so it was not changed.')
            ->toContain('holds 1 and 0');
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('prints the block and writes nothing with --dry-run', function () {
        $root = agentsMdProject();

        runAgentsMd($root, ['dry-run' => true]);

        expect(file_exists($root . '/AGENTS.md'))->toBeFalse();
        expect(agentsMdMessages('wp_cli_line')[0])->toBe(agentsMdBlock($root));
        expect(agentsMdMessages('wp_cli_log'))->toBe([
            'Dry run, nothing was written. Without --dry-run: Created AGENTS.md with the Føhn block.',
        ]);
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('prints the block with --dry-run when it is already current', function () {
        $root = agentsMdProject();
        runAgentsMd($root);
        $contents = file_get_contents($root . '/AGENTS.md');
        wp_stub_reset();

        runAgentsMd($root, ['dry-run' => true]);

        expect(file_get_contents($root . '/AGENTS.md'))->toBe($contents);
        expect(agentsMdMessages('wp_cli_line')[0])->toBe(agentsMdBlock($root));
        expect(agentsMdMessages('wp_cli_log'))->toBe([
            'Dry run, nothing was written. Without --dry-run: The Føhn block in AGENTS.md is up to date.',
        ]);
    });

    it('stops when it cannot find the project root', function () {
        $root = sys_get_temp_dir() . '/foehn-tests/no-project-' . bin2hex(random_bytes(4));
        mkdir($root . '/app', 0o777, true);

        runAgentsMd($root);

        expect(agentsMdMessages('wp_cli_error')[0])->toStartWith('Could not find the project root');
        expect(file_exists($root . '/AGENTS.md'))->toBeFalse();
    });
});
