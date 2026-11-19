<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;

describe('foehn.config.php', function () {
    it('blocks publication on accessibility issues, in every environment', function (string $file) {
        /** @var FoehnConfig $config */
        $config = require dirname(__DIR__, 4) . '/theme/app/' . $file;

        expect($config->blockPublicationOnAccessibilityIssues)->toBeTrue();
    })->with(['foehn.config.php', 'foehn.local.config.php']);
});
