<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\Log;

describe('Log', function () {
    beforeEach(function () {
        $this->logs = [];
        Log::setHandler(function (string $message) {
            $this->logs[] = $message;
        });
    });

    afterEach(function () {
        Log::setHandler(null);
        Log::setEnabled(null);
    });

    describe('when logging is disabled', function () {
        beforeEach(function () {
            Log::setEnabled(false);
        });

        it('does not log anything', function () {
            Log::info('Test message');

            expect($this->logs)->toBeEmpty();
        });
    });

    describe('when logging is enabled', function () {
        beforeEach(function () {
            Log::setEnabled(true);
        });

        it('logs info messages', function () {
            Log::info('Test message');

            expect($this->logs)->toHaveCount(1);
            expect($this->logs[0])->toContain('[FOEHN.INFO]');
            expect($this->logs[0])->toContain('Test message');
        });

        it('logs error messages', function () {
            Log::error('Error occurred');

            expect($this->logs[0])->toContain('[FOEHN.ERROR]');
        });

        it('logs warning messages', function () {
            Log::warning('Warning message');

            expect($this->logs[0])->toContain('[FOEHN.WARNING]');
        });

        it('logs debug messages', function () {
            Log::debug('Debug info');

            expect($this->logs[0])->toContain('[FOEHN.DEBUG]');
        });

        it('logs critical messages', function () {
            Log::critical('Critical error');

            expect($this->logs[0])->toContain('[FOEHN.CRITICAL]');
        });

        it('logs emergency messages', function () {
            Log::emergency('System down');

            expect($this->logs[0])->toContain('[FOEHN.EMERGENCY]');
        });

        it('logs alert messages', function () {
            Log::alert('Alert message');

            expect($this->logs[0])->toContain('[FOEHN.ALERT]');
        });

        it('logs notice messages', function () {
            Log::notice('Notice message');

            expect($this->logs[0])->toContain('[FOEHN.NOTICE]');
        });

        it('includes context in log message', function () {
            Log::info('User action', ['user_id' => 123, 'action' => 'login']);

            expect($this->logs[0])->toContain('"user_id":123');
            expect($this->logs[0])->toContain('"action":"login"');
        });

        it('includes timestamp in log message', function () {
            Log::info('Test');

            // Should have format [YYYY-MM-DD HH:MM:SS]
            expect($this->logs[0])->toMatch('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/');
        });

        it('handles empty context', function () {
            Log::info('Simple message');

            expect($this->logs[0])->not->toContain('{}');
            expect($this->logs[0])->toContain('Simple message');
        });
    });
});
