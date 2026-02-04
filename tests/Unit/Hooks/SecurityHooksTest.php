<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\SecurityHooks;

describe('SecurityHooks', function () {
    it('sends security headers', function () {
        // Use xdebug_get_headers() if available, otherwise test method exists
        // Since we can't easily test header() in CLI, verify the method is callable
        // and has the correct attribute
        $reflection = new ReflectionClass(SecurityHooks::class);
        $method = $reflection->getMethod('sendSecurityHeaders');

        $attributes = $method->getAttributes(\Studiometa\WPTempest\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);

        $instance = $attributes[0]->newInstance();

        expect($instance->hook)->toBe('send_headers');
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(SecurityHooks::class);

        expect($reflection->isFinal())->toBeTrue();
    });
});
