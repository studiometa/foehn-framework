<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Skills\AgentsBlock;
use Studiometa\Foehn\Skills\SkillCatalog;

/**
 * The starter ships AGENTS.md with the Føhn block already written, so a new project
 * has it before anyone runs `wp foehn agents-md`. That block is what a project made
 * from the starter gets: the skills of studiometa/foehn and of the Vite plugin, read
 * from this monorepo.
 *
 * When this test fails, a skill or the directive changed: replace the block in
 * packages/starter/AGENTS.md with the expected one this test prints in its diff.
 */
describe('the starter AGENTS.md', function () {
    $monorepo = dirname(__DIR__, 5);
    $agents = $monorepo . '/packages/starter/AGENTS.md';

    afterEach(fn() => removeTestDirectory(sys_get_temp_dir() . '/foehn-tests'));

    it('holds the block that the installed starter packages give', function () use ($monorepo, $agents) {
        $root = testProjectRoot([
            'vendor/composer/installed.json' => json_encode([
                'packages' => [
                    ['name' => 'studiometa/foehn', 'install-path' => '../studiometa/foehn'],
                    ['name' => 'studiometa/foehn-installer', 'install-path' => '../studiometa/foehn-installer'],
                ],
            ]),
        ]);
        $links = [
            'vendor/studiometa/foehn' => 'packages/foehn',
            'vendor/studiometa/foehn-installer' => 'packages/installer',
            'node_modules/@studiometa/foehn-vite-plugin' => 'packages/vite-plugin',
        ];

        foreach ($links as $link => $target) {
            if (!is_dir(dirname($root . '/' . $link))) {
                mkdir(dirname($root . '/' . $link), 0o777, true);
            }

            symlink($monorepo . '/' . $target, $root . '/' . $link);
        }

        try {
            $block = new AgentsBlock(new SkillCatalog(new DiscoveryLocations($root . '/app')))->render();
        } finally {
            // removeTestDirectory() does not remove a link to a directory.
            foreach (array_keys($links) as $link) {
                unlink($root . '/' . $link);
            }
        }

        $contents = (string) file_get_contents($agents);
        $start = (int) strpos($contents, AgentsBlock::BEGIN);
        $end = (int) strpos($contents, AgentsBlock::END) + strlen(AgentsBlock::END);

        expect(substr($contents, $start, $end - $start))->toBe($block);
        // AGENTS.md is read whole at the start of every task.
        expect(substr_count($block, "\n") + 1)->toBeLessThan(60);
    })->skip(fn() => !is_file($agents), 'Runs in the monorepo only.');
});
