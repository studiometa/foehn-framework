<?php

declare(strict_types=1);

use Studiometa\FoehnInstaller\InstallerConfig;
use Studiometa\FoehnInstaller\PublicFiles;
use Studiometa\FoehnInstaller\WebRootGenerator;

/**
 * Write a file below the project root, creating its directories.
 */
function writeProjectFile(string $root, string $path, string $content): void
{
    $file = $root . '/' . $path;

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0o777, true);
    }

    file_put_contents($file, $content);
}

beforeEach(function () {
    $this->io = recordingIo();
    $this->root = makeProjectRoot('theme', 'public');

    $this->generate = function (array $extra = []): void {
        $config = InstallerConfig::fromArray($extra, $this->root);
        new WebRootGenerator($this->io, $this->root, $config)->generate();
    };
});

afterEach(fn() => removeDirectory($this->root));

describe('PublicFiles', function () {
    it('copies the public directory into the web root, dotfiles included', function () {
        writeProjectFile($this->root, 'public/robots.txt', "User-agent: *\n");
        writeProjectFile($this->root, 'public/.user.ini', "auto_prepend_file = waf.php\n");
        writeProjectFile($this->root, 'public/.well-known/security.txt', "Contact: mailto:security@example.com\n");

        ($this->generate)();

        expect(file_get_contents($this->root . '/web/robots.txt'))->toBe("User-agent: *\n");
        expect(file_get_contents($this->root . '/web/.user.ini'))->toBe("auto_prepend_file = waf.php\n");
        expect(file_get_contents($this->root . '/web/.well-known/security.txt'))
            ->toBe("Contact: mailto:security@example.com\n");
    });

    it('copies the files, never a symlink to them', function () {
        writeProjectFile($this->root, 'public/robots.txt', 'robots');

        ($this->generate)();

        expect(is_link($this->root . '/web/robots.txt'))->toBeFalse();
        expect(is_link($this->root . '/web'))->toBeFalse();
    });

    it('reads the directory the project named', function () {
        writeProjectFile($this->root, 'static/ads.txt', 'ads');

        ($this->generate)(['public-dir' => 'static']);

        expect(file_get_contents($this->root . '/web/ads.txt'))->toBe('ads');
    });

    it('does nothing for a project without a public directory', function () {
        rmdir($this->root . '/public');

        ($this->generate)();

        expect(file_exists($this->root . '/web/' . PublicFiles::MANIFEST))->toBeFalse();
        expect($this->io->getOutput())->not->toContain('public');
    });

    it('keeps the generated files over a project file of the same name, with a warning', function () {
        writeProjectFile($this->root, 'public/index.php', '<?php echo "project";');
        writeProjectFile($this->root, 'public/wp-config.php', '<?php echo "project";');
        writeProjectFile($this->root, 'public/wp-content/mu-plugins/00-loader.php', '<?php echo "project";');
        writeProjectFile($this->root, 'public/wp-content/advanced-cache.php', '<?php echo "project";');
        writeProjectFile($this->root, 'public/wp/wp-settings.php', '<?php echo "project";');

        ($this->generate)();

        expect(file_get_contents($this->root . '/web/index.php'))->toContain('wp-blog-header.php');
        expect(file_get_contents($this->root . '/web/wp-config.php'))->toContain('DB_NAME');
        expect(file_get_contents($this->root . '/web/wp-content/advanced-cache.php'))->toContain('PageCache');
        expect(file_exists($this->root . '/web/wp-content/mu-plugins/00-loader.php'))->toBeFalse();
        expect(file_exists($this->root . '/web/wp/wp-settings.php'))->toBeFalse();

        expect($this->io->getOutput())
            ->toContain('public/index.php — the installer owns')
            ->toContain('public/wp-config.php — the installer owns')
            ->toContain('public/wp-content/advanced-cache.php — the installer owns')
            ->toContain('public/wp-content/mu-plugins/00-loader.php — the installer owns')
            ->toContain('public/wp/wp-settings.php — the installer owns');
    });

    it('never writes through the theme symlink', function () {
        writeProjectFile($this->root, 'public/wp-content/themes/theme/functions.php', '<?php // project');

        ($this->generate)();

        expect(file_exists($this->root . '/theme/functions.php'))->toBeFalse();
    });

    it('removes a file deleted from the public directory on the next install', function () {
        writeProjectFile($this->root, 'public/robots.txt', 'robots');
        writeProjectFile($this->root, 'public/.well-known/security.txt', 'security');

        ($this->generate)();

        unlink($this->root . '/public/.well-known/security.txt');
        rmdir($this->root . '/public/.well-known');

        ($this->generate)();

        expect(file_exists($this->root . '/web/robots.txt'))->toBeTrue();
        expect(file_exists($this->root . '/web/.well-known/security.txt'))->toBeFalse();
        expect(is_dir($this->root . '/web/.well-known'))->toBeFalse();
        expect($this->io->getOutput())->toContain('Removed: web/.well-known/security.txt');
    });

    it('removes every copied file when the public directory goes away', function () {
        writeProjectFile($this->root, 'public/robots.txt', 'robots');

        ($this->generate)();

        removeDirectory($this->root . '/public');

        ($this->generate)();

        expect(file_exists($this->root . '/web/robots.txt'))->toBeFalse();
        expect(file_exists($this->root . '/web/' . PublicFiles::MANIFEST))->toBeFalse();
    });

    it('leaves alone a file of the web root it did not copy', function () {
        writeProjectFile($this->root, 'public/robots.txt', 'robots');

        ($this->generate)();

        file_put_contents($this->root . '/web/written-by-a-plugin.txt', 'plugin');

        ($this->generate)();

        expect(file_exists($this->root . '/web/written-by-a-plugin.txt'))->toBeTrue();
    });

    it('keeps a directory that still holds a file it did not copy', function () {
        writeProjectFile($this->root, 'public/.well-known/security.txt', 'security');

        ($this->generate)();

        file_put_contents($this->root . '/web/.well-known/acme.txt', 'acme');
        removeDirectory($this->root . '/public/.well-known');

        ($this->generate)();

        expect(file_exists($this->root . '/web/.well-known/security.txt'))->toBeFalse();
        expect(file_exists($this->root . '/web/.well-known/acme.txt'))->toBeTrue();
    });

    it('ignores a list entry that leaves the web root', function () {
        writeProjectFile($this->root, 'outside.txt', 'keep me');
        writeProjectFile($this->root, 'public/robots.txt', 'robots');

        ($this->generate)();

        file_put_contents(
            $this->root . '/web/' . PublicFiles::MANIFEST,
            "<?php return ['../outside.txt', 'index.php', 'robots.txt'];",
        );

        ($this->generate)();

        expect(file_exists($this->root . '/outside.txt'))->toBeTrue();
        expect(file_exists($this->root . '/web/index.php'))->toBeTrue();
    });

    it('updates a copied file whose source changed', function () {
        writeProjectFile($this->root, 'public/robots.txt', 'first');

        ($this->generate)();

        file_put_contents($this->root . '/public/robots.txt', 'second');

        ($this->generate)();

        expect(file_get_contents($this->root . '/web/robots.txt'))->toBe('second');
    });

    it('skips a symlink in the public directory, which could point anywhere', function () {
        writeProjectFile($this->root, '.env', 'DB_PASSWORD=secret');
        symlink($this->root . '/.env', $this->root . '/public/leak.txt');

        ($this->generate)();

        expect(file_exists($this->root . '/web/leak.txt'))->toBeFalse();
        expect($this->io->getOutput())->toContain('public/leak.txt is a symlink');
    });

    it('refuses a public directory outside the project', function () {
        $outside = makeProjectRoot();
        writeProjectFile($outside, 'secret.txt', 'secret');

        ($this->generate)(['public-dir' => '../' . basename($outside)]);

        expect(file_exists($this->root . '/web/secret.txt'))->toBeFalse();
        expect($this->io->getOutput())->toContain('is not inside the project');

        removeDirectory($outside);
    });

    it('refuses the project root itself', function () {
        writeProjectFile($this->root, '.env', 'DB_PASSWORD=secret');

        ($this->generate)(['public-dir' => '.']);

        expect(file_exists($this->root . '/web/.env'))->toBeFalse();
        expect($this->io->getOutput())->toContain('is not inside the project');
    });

    it('refuses a public directory that is the web root or inside it', function (string $publicDir) {
        ($this->generate)();
        ($this->generate)(['public-dir' => $publicDir]);

        expect($this->io->getOutput())->toContain('overlaps the web root');
        expect(file_exists($this->root . '/web/' . PublicFiles::MANIFEST))->toBeFalse();
    })->with(['web', 'web/wp-content', 'web/wp-content/uploads']);

    it('refuses a public directory that holds the web root', function () {
        writeProjectFile($this->root, 'site/robots.txt', 'robots');

        ($this->generate)(['web-dir' => 'site/web', 'public-dir' => 'site']);

        expect(file_exists($this->root . '/site/web/robots.txt'))->toBeFalse();
        expect($this->io->getOutput())->toContain('overlaps the web root');
    });
});
