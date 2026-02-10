<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\TimberConfig;

describe('TimberConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new TimberConfig();

        expect($config->templatesDir)->toBe(['templates']);
    });

    it('can be instantiated with custom values', function () {
        $config = new TimberConfig(templatesDir: ['views', 'twig-templates']);

        expect($config->templatesDir)->toBe(['views', 'twig-templates']);
    });

    it('is readonly', function () {
        expect(TimberConfig::class)->toBeReadonly();
    });
});
