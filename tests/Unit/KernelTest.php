<?php

declare(strict_types=1);

use Studiometa\WPTempest\Kernel;
use Studiometa\WPTempest\Config\WpTempestConfig;
use Timber\Timber;

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

describe('Kernel Timber initialization', function () {
    afterEach(function () {
        Kernel::reset();
        wp_stub_reset();
    });

    it('calls initializeTimber during bootstrap', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src');

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        // Timber::$dirname should be set to the default config value
        expect(Timber::$dirname)->toBe(['templates']);
    });

    it('sets Timber dirname from config', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', [
            'timber_templates_dir' => ['views', 'twig-templates'],
        ]);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        expect(Timber::$dirname)->toBe(['views', 'twig-templates']);
    });

    it('registers timber/context filter', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src');

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        $contextFilters = wp_stub_get_calls('add_filter');
        $timberContextFilter = array_filter(
            $contextFilters,
            fn(array $call) => $call['args']['hook'] === 'timber/context',
        );

        expect($timberContextFilter)->not->toBeEmpty();
    });

    it('uses default templates dir of ["templates"]', function () {
        $config = WpTempestConfig::fromArray([]);

        expect($config->timberTemplatesDir)->toBe(['templates']);
    });
});
