<?php

declare(strict_types=1);

use Studiometa\Foehn\Security\Salts;

describe('Salts', function () {
    it('covers the eight keys WordPress expects', function () {
        expect(Salts::NAMES)->toBe([
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ]);
    });

    it('generates a value for every key', function () {
        expect(array_keys(Salts::generate()->values))->toBe(Salts::NAMES);
    });

    it('generates values long enough to sign with', function () {
        foreach (Salts::generate()->values as $value) {
            expect(strlen($value))->toBeGreaterThanOrEqual(64);
        }
    });

    it('never repeats a value within a set', function () {
        $values = Salts::generate()->values;

        expect(array_unique($values))->toHaveCount(count(Salts::NAMES));
    });

    it('never repeats a value between sets', function () {
        // Two installs of the same project must not end up signing with the same
        // keys, which is exactly what the old md5(__DIR__) fallback did.
        expect(array_intersect(Salts::generate()->values, Salts::generate()->values))->toBe([]);
    });

    it('writes a PHP file that defines each key', function () {
        $salts = Salts::generate();
        $file = $salts->toPhpFile();

        expect($file)->toStartWith('<?php');

        foreach ($salts->values as $name => $value) {
            expect($file)->toContain(sprintf("define('%s', '%s');", $name, $value));
        }
    });

    it('writes a file that parses, and defines what it says', function () {
        $salts = Salts::generate();
        $path = sys_get_temp_dir() . '/foehn-tests/salts-' . uniqid('', true) . '.php';

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }

        file_put_contents($path, $salts->toPhpFile());

        try {
            // Run it in a separate process: the constants can only be defined once,
            // and a syntax error here would only show up in a booting wp-config.
            $script = sprintf('require %s; echo AUTH_KEY;', var_export($path, true));

            $output = [];
            $status = 0;
            exec('php -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

            expect($status)->toBe(0, implode("\n", $output));
            expect($output[0] ?? '')->toBe($salts->values['AUTH_KEY']);
        } finally {
            unlink($path);
        }
    });

    it('says what a value nobody should trust looks like', function () {
        // The generated wp-config.php treats a value with this prefix as absent.
        expect(Salts::PLACEHOLDER_PREFIX)->toBe('change-me-');

        foreach (Salts::generate()->values as $value) {
            expect($value)->not->toStartWith(Salts::PLACEHOLDER_PREFIX);
        }
    });
});
