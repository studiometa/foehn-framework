<?php

declare(strict_types=1);

use Studiometa\WPTempest\Kernel;
use Studiometa\WPTempest\Config\WpTempestConfig;

describe('Kernel', function () {
    afterEach(function () {
        // Reset the singleton after each test
        Kernel::reset();
    });

    it('throws exception when getInstance called before boot', function () {
        expect(fn() => Kernel::getInstance())
            ->toThrow(RuntimeException::class, 'Kernel not booted. Call Kernel::boot() first.');
    });

    it('can be reset', function () {
        // First verify getInstance throws before boot
        expect(fn() => Kernel::getInstance())->toThrow(RuntimeException::class);

        // Reset is already called in afterEach, but we can verify it works
        Kernel::reset();

        expect(fn() => Kernel::getInstance())->toThrow(RuntimeException::class);
    });
});

describe('Kernel configuration', function () {
    afterEach(function () {
        Kernel::reset();
    });

    it('stores app path', function () {
        // Use reflection to test without full WordPress environment
        $reflection = new ReflectionClass(Kernel::class);
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);

        $kernel = $reflection->newInstanceWithoutConstructor();
        $appPathProperty = $reflection->getProperty('appPath');
        $appPathProperty->setValue($kernel, '/path/to/app');

        expect($kernel->getAppPath())->toBe('/path/to/app');
    });

    it('returns config values with defaults', function () {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($kernel, ['key' => 'value', 'nested' => ['a' => 1]]);

        expect($kernel->getConfig('key'))->toBe('value');
        expect($kernel->getConfig('nested'))->toBe(['a' => 1]);
        expect($kernel->getConfig('missing'))->toBeNull();
        expect($kernel->getConfig('missing', 'default'))->toBe('default');
    });

    it('returns wp tempest config', function () {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();

        $config = WpTempestConfig::fromArray(['discovery_cache' => 'full']);
        $configProperty = $reflection->getProperty('wpTempestConfig');
        $configProperty->setValue($kernel, $config);

        expect($kernel->getWpTempestConfig())->toBe($config);
    });

    it('tracks booted state', function () {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();

        $bootedProperty = $reflection->getProperty('booted');
        $bootedProperty->setValue($kernel, false);

        expect($kernel->isBooted())->toBeFalse();

        $bootedProperty->setValue($kernel, true);

        expect($kernel->isBooted())->toBeTrue();
    });
});

describe('Kernel::findProjectRoot', function () {
    /**
     * Helper to call the private static method via reflection.
     */
    function callFindProjectRoot(string $path): string
    {
        $method = new ReflectionMethod(Kernel::class, 'findProjectRoot');

        return $method->invoke(null, $path);
    }

    it('finds the project root from a subdirectory', function () {
        // The project root is where this repo's composer.json lives
        $projectRoot = dirname(__DIR__, 2);
        $subDir = $projectRoot . '/src/Attributes';

        expect(callFindProjectRoot($subDir))->toBe($projectRoot);
    });

    it('finds the project root when given the root itself', function () {
        $projectRoot = dirname(__DIR__, 2);

        expect(callFindProjectRoot($projectRoot))->toBe($projectRoot);
    });

    it('throws when the path does not exist', function () {
        callFindProjectRoot('/non/existent/path');
    })->throws(RuntimeException::class, 'Path does not exist');

    it('throws when composer.json is not found', function () {
        callFindProjectRoot('/');
    })->throws(RuntimeException::class, 'Could not locate project root');
});
