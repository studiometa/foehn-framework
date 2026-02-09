<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\AcfConfig;

describe('AcfConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new AcfConfig();

        expect($config->transformFields)->toBeTrue();
    });

    it('can be instantiated with transform fields disabled', function () {
        $config = new AcfConfig(transformFields: false);

        expect($config->transformFields)->toBeFalse();
    });

    it('is readonly', function () {
        expect(AcfConfig::class)->toBeReadonly();
    });
});
