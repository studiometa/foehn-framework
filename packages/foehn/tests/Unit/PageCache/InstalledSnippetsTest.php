<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\ServerConfig\InstalledSnippets;

describe('InstalledSnippets', function () {
    beforeEach(function () {
        $this->project = pageCacheRoot() . '/project';

        foreach (InstalledSnippets::DIRECTORIES as $directory) {
            mkdir($this->project . '/' . $directory, 0o777, true);
        }

        $this->write = function (string $path, string $contents): void {
            file_put_contents($this->project . '/' . $path, $contents);
        };
    });

    afterEach(function () {
        removeTestDirectory(dirname($this->project));
    });

    it('finds a Føhn snippet whatever its file name, in both directories', function () {
        ($this->write)('config/nginx/page-cache.conf', "# Foehn page cache — generated.\n# policy: aaaaaaaaaaaa\n");
        ($this->write)('.ddev/nginx/cache.conf', "# Foehn page cache — generated.\n# policy: bbbbbbbbbbbb\n");

        expect(InstalledSnippets::nginx($this->project, 'aaaaaaaaaaaa'))->toBe([
            'config/nginx/page-cache.conf' => true,
            '.ddev/nginx/cache.conf' => false,
        ]);
    });

    it('ignores the other nginx files of the project', function () {
        ($this->write)('config/nginx/redirects.conf', "rewrite ^/old$ /new permanent;\n");
        ($this->write)('config/nginx/page-cache.conf.bak', "# Foehn page cache\n# policy: aaaaaaaaaaaa\n");

        expect(InstalledSnippets::nginx($this->project, 'aaaaaaaaaaaa'))->toBe([]);
    });

    it('finds nothing in a project without the directories', function () {
        expect(InstalledSnippets::nginx(dirname($this->project) . '/missing', 'aaaaaaaaaaaa'))->toBe([]);
    });
});
