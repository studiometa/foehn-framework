<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\Commands\SaltsGenerateCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Security\Salts;

beforeEach(function () {
    wp_stub_reset();

    // WP_CONTENT_DIR is <temp>/foehn-tests/web/wp-content, so the project root the
    // command works out is <temp>/foehn-tests — where a real install keeps its .env.
    $this->root = dirname((string) constant('WP_CONTENT_DIR'), 2);
    $this->env = $this->root . '/.env';
    $this->php = $this->root . '/config/wordpress-salts.config.php';
    $this->command = new SaltsGenerateCommand(new WpCli());

    $this->logged = fn(): string => implode("\n", array_column(
        array_column(wp_stub_get_calls('wp_cli_warning'), 'args'),
        'message',
    ));
});

afterEach(function () {
    foreach ([$this->env, $this->php] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

describe('salts:generate', function () {
    it('writes the keys to .env by default', function () {
        ($this->command)([], []);

        expect($this->env)->toBeFile();

        foreach (Salts::NAMES as $name) {
            expect(file_get_contents($this->env))->toMatch('/^' . $name . '="[^"]{64,}"$/m');
        }

        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
    });

    it('leaves the rest of .env untouched', function () {
        file_put_contents($this->env, "DB_NAME=db\nWP_DEBUG=true\n");

        ($this->command)([], []);

        expect(file_get_contents($this->env))->toContain('DB_NAME=db')->toContain('WP_DEBUG=true');
    });

    it('fills in a name that .env lists empty rather than adding it twice', function () {
        file_put_contents($this->env, "AUTH_KEY=\nDB_NAME=db\n");

        ($this->command)([], []);

        expect(preg_match_all('/^AUTH_KEY=/m', file_get_contents($this->env)))->toBe(1);
        expect(file_get_contents($this->env))->toMatch('/^AUTH_KEY="[^"]{64,}"$/m');
    });

    it('refuses to replace keys that are set, without being told twice', function () {
        file_put_contents($this->env, "AUTH_KEY=\"mine\"\n");

        ($this->command)([], []);

        // Replacing keys ends every session signed with the old ones.
        expect(file_get_contents($this->env))->toContain('AUTH_KEY="mine"');
        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('replaces them when forced', function () {
        file_put_contents($this->env, "AUTH_KEY=\"mine\"\n");

        ($this->command)([], ['force' => true, 'yes' => true]);

        expect(file_get_contents($this->env))->not->toContain('AUTH_KEY="mine"');
        expect(file_get_contents($this->env))->toMatch('/^AUTH_KEY="[^"]{64,}"$/m');
    });

    it('generates different keys each time it runs', function () {
        ($this->command)([], []);
        $first = file_get_contents($this->env);

        ($this->command)([], ['force' => true, 'yes' => true]);

        expect(file_get_contents($this->env))->not->toBe($first);
    });

    it('writes a PHP file when the path asks for one', function () {
        ($this->command)([], ['path' => 'config/wordpress-salts.config.php']);

        expect($this->php)->toBeFile();
        expect(file_get_contents($this->php))->toContain("define('AUTH_KEY',");
        expect(decoct(fileperms($this->php) & 0o777))->toBe('600');
    });

    it('replaces a PHP file when forced', function () {
        ($this->command)([], ['path' => 'config/wordpress-salts.config.php']);
        $first = file_get_contents($this->php);

        ($this->command)([], ['path' => 'config/wordpress-salts.config.php', 'force' => true, 'yes' => true]);

        expect(file_get_contents($this->php))->not->toBe($first);
    });

    it('refuses to replace a PHP file without being told twice', function () {
        ($this->command)([], ['path' => 'config/wordpress-salts.config.php']);
        $first = file_get_contents($this->php);

        ($this->command)([], ['path' => 'config/wordpress-salts.config.php']);

        expect(file_get_contents($this->php))->toBe($first);
        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('says nothing about permissions on a file only its owner can read', function () {
        ($this->command)([], []);
        chmod($this->env, 0o600);

        wp_stub_reset();
        ($this->command)([], ['force' => true, 'yes' => true]);

        expect(($this->logged)())->not->toContain('readable by other users');
    });

    it('says so when the keys landed in a file others can read', function () {
        ($this->command)([], []);
        chmod($this->env, 0o644);

        wp_stub_reset();
        ($this->command)([], ['force' => true, 'yes' => true]);

        expect(($this->logged)())->toContain('readable by other users');
    });

    it('takes a relative path as relative to the project', function () {
        ($this->command)([], ['path' => 'my.env']);

        expect($this->root . '/my.env')->toBeFile();

        unlink($this->root . '/my.env');
    });

    it('says so when a PHP file would win over the keys it just wrote', function () {
        if (!is_dir(dirname($this->php))) {
            mkdir(dirname($this->php), 0o777, true);
        }

        file_put_contents($this->php, "<?php\n");

        ($this->command)([], []);

        // wp-config.php requires that file before reading the environment, so the
        // keys in .env would change nothing WordPress sees.
        expect(($this->logged)())->toContain('read first');
    });

    it('says nothing about a PHP file that is not there', function () {
        ($this->command)([], []);

        expect(($this->logged)())->not->toContain('read first');
    });

    it('falls back to the default when handed an empty path', function () {
        ($this->command)([], ['path' => '']);

        expect($this->env)->toBeFile();
    });
});
