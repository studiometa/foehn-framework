<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\Commands\SaltsGenerateCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Security\Salts;

beforeEach(function () {
    wp_stub_reset();

    $this->directory = sys_get_temp_dir() . '/foehn-tests/salts-command-' . uniqid('', true);
    $this->path = $this->directory . '/config/wordpress-salts.config.php';
    $this->command = new SaltsGenerateCommand(new WpCli());
});

afterEach(fn() => removeTestDirectory($this->directory));

describe('salts:generate', function () {
    it('writes the keys where it was told to', function () {
        ($this->command)([], ['path' => $this->path]);

        expect($this->path)->toBeFile();

        foreach (Salts::NAMES as $name) {
            expect(file_get_contents($this->path))->toContain("define('{$name}',");
        }

        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
    });

    it('creates the directory it writes into', function () {
        expect(is_dir(dirname($this->path)))->toBeFalse();

        ($this->command)([], ['path' => $this->path]);

        expect(is_dir(dirname($this->path)))->toBeTrue();
    });

    it('keeps the file readable by its owner alone', function () {
        ($this->command)([], ['path' => $this->path]);

        expect(decoct(fileperms($this->path) & 0o777))->toBe('600');
    });

    it('refuses to replace existing keys without being told twice', function () {
        mkdir(dirname($this->path), 0o777, true);
        file_put_contents($this->path, "<?php // mine\n");

        ($this->command)([], ['path' => $this->path]);

        // Replacing keys logs every user out, so it does not happen by accident.
        expect(file_get_contents($this->path))->toContain('// mine');
        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('replaces existing keys when forced', function () {
        mkdir(dirname($this->path), 0o777, true);
        file_put_contents($this->path, "<?php // mine\n");

        ($this->command)([], ['path' => $this->path, 'force' => true, 'yes' => true]);

        expect(file_get_contents($this->path))->not->toContain('// mine');
        expect(file_get_contents($this->path))->toContain("define('AUTH_KEY',");
    });

    it('generates different keys each time it runs', function () {
        ($this->command)([], ['path' => $this->path]);
        $first = file_get_contents($this->path);

        ($this->command)([], ['path' => $this->path, 'force' => true, 'yes' => true]);
        $second = file_get_contents($this->path);

        expect($second)->not->toBe($first);
    });

    it('defaults to the file the generated wp-config reads', function () {
        ($this->command)([], []);

        // WP_CONTENT_DIR is <temp>/foehn-tests/wp-content, so the project root is
        // two levels up — where the installer puts the config directory.
        $expected = dirname((string) constant('WP_CONTENT_DIR'), 2) . '/config/wordpress-salts.config.php';

        expect($expected)->toBeFile();

        unlink($expected);
    });

    it('falls back to the default when handed an empty path', function () {
        ($this->command)([], ['path' => '']);

        $expected = dirname((string) constant('WP_CONTENT_DIR'), 2) . '/config/wordpress-salts.config.php';

        expect($expected)->toBeFile();

        unlink($expected);
    });
});
