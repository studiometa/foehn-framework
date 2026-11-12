<?php

declare(strict_types=1);

use Studiometa\Foehn\DebugLog\DebugLog;
use Studiometa\Foehn\DebugLog\LogCommand;
use Studiometa\Foehn\Kernel;

describe('Kernel: wp foehn log', function () {
    afterEach(function () {
        Kernel::reset();
        wp_stub_reset();
    });

    it('builds the command WP-CLI resolves, with the log of the WordPress constants', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(LogCommand::class))->toBeInstanceOf(LogCommand::class);
        expect(Kernel::get(DebugLog::class)->path)->toBeNull();
    });
});
