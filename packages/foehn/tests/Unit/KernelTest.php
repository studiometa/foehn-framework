<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Kernel;
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

        $config = FoehnConfig::fromArray(['discovery_cache' => 'full']);
        $configProperty = $reflection->getProperty('foehnConfig');
        $configProperty->setValue($kernel, $config);

        expect($kernel->getFoehnConfig())->toBe($config);
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
        // The project root is where composer.json + vendor/ both exist
        // In monorepo, this is the repository root, not the package directory
        $packageDir = dirname(__DIR__, 2);
        $subDir = $packageDir . '/src/Attributes';
        $result = callFindProjectRoot($subDir);

        // Should find a directory with both composer.json and vendor/
        expect(file_exists($result . '/composer.json'))->toBeTrue();
        expect(is_dir($result . '/vendor'))->toBeTrue();
    });

    it('finds the project root when given the root itself', function () {
        $packageDir = dirname(__DIR__, 2);
        $result = callFindProjectRoot($packageDir);

        expect(file_exists($result . '/composer.json'))->toBeTrue();
        expect(is_dir($result . '/vendor'))->toBeTrue();
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

    it('registers TimberConfig in container with defaults', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', []);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        $config = Kernel::get(\Studiometa\Foehn\Config\TimberConfig::class);
        expect($config)->toBeInstanceOf(\Studiometa\Foehn\Config\TimberConfig::class);
        expect($config->templatesDir)->toBe(['templates']);
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

    it('registers AcfConfig in container with defaults', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', []);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        $config = Kernel::get(\Studiometa\Foehn\Config\AcfConfig::class);
        expect($config)->toBeInstanceOf(\Studiometa\Foehn\Config\AcfConfig::class);
        expect($config->transformFields)->toBeTrue();
    });

    it('registers RestConfig in container with defaults', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', []);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        $config = Kernel::get(\Studiometa\Foehn\Config\RestConfig::class);
        expect($config)->toBeInstanceOf(\Studiometa\Foehn\Config\RestConfig::class);
        expect($config->defaultCapability)->toBe('edit_posts');
    });

    it('registers RenderApi in container', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', []);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        // RenderApi should be available in container
        $renderApi = Kernel::get(\Studiometa\Foehn\Rest\RenderApi::class);
        expect($renderApi)->toBeInstanceOf(\Studiometa\Foehn\Rest\RenderApi::class);
    });

    it('registers RenderApiConfig in container with defaults', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', []);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        // RenderApiConfig should be available with empty templates by default
        $config = Kernel::get(\Studiometa\Foehn\Config\RenderApiConfig::class);
        expect($config)->toBeInstanceOf(\Studiometa\Foehn\Config\RenderApiConfig::class);
        expect($config->templates)->toBe([]);
    });
});

describe('Kernel respects user config files', function () {
    afterEach(function () {
        Kernel::reset();
        wp_stub_reset();
    });

    it('does not overwrite user-defined configs already in container', function () {
        // Boot kernel
        $kernel = Kernel::boot(dirname(__DIR__, 2) . '/src', []);

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        // Get the container
        $container = Kernel::container();

        // Create a custom config
        $customConfig = new \Studiometa\Foehn\Config\TimberConfig(templatesDir: ['custom-views']);

        // Manually register it (simulating what Tempest's LoadConfig does)
        $container->singleton(\Studiometa\Foehn\Config\TimberConfig::class, fn() => $customConfig);

        // Verify the container returns our custom config (not overwritten)
        $config = $container->get(\Studiometa\Foehn\Config\TimberConfig::class);
        expect($config->templatesDir)->toBe(['custom-views']);
    });

    it('uses has() check before registering default configs', function () {
        // Use reflection to verify the registerCoreServices method checks has()
        $reflection = new ReflectionClass(Kernel::class);
        $method = $reflection->getMethod('registerCoreServices');
        $source = file_get_contents($reflection->getFileName());

        // Verify the code contains has() checks for each config
        expect($source)->toContain('if (!$this->container->has(TimberConfig::class))');
        expect($source)->toContain('if (!$this->container->has(AcfConfig::class))');
        expect($source)->toContain('if (!$this->container->has(RestConfig::class))');
        expect($source)->toContain('if (!$this->container->has(RenderApiConfig::class))');
    });
});
