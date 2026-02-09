<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\RestConfig;

describe('RestConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new RestConfig();

        expect($config->defaultCapability)->toBe('edit_posts');
    });

    it('can be instantiated with custom capability', function () {
        $config = new RestConfig(defaultCapability: 'manage_options');

        expect($config->defaultCapability)->toBe('manage_options');
    });

    it('can be instantiated with null capability for logged-in only', function () {
        $config = new RestConfig(defaultCapability: null);

        expect($config->defaultCapability)->toBeNull();
    });

    it('is readonly', function () {
        expect(RestConfig::class)->toBeReadonly();
    });
});
