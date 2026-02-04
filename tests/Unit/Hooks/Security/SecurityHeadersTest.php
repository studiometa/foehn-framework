<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Security\SecurityHeaders;

describe('SecurityHeaders', function () {
    it('has AsAction attribute on send_headers hook', function () {
        $method = new ReflectionMethod(SecurityHeaders::class, 'sendSecurityHeaders');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('send_headers');
    });

    it('is a final class', function () {
        expect(new ReflectionClass(SecurityHeaders::class))->isFinal()->toBeTrue();
    });

    it('does not send deprecated X-XSS-Protection header', function () {
        // Verify the method source does not contain X-XSS-Protection
        $method = new ReflectionMethod(SecurityHeaders::class, 'sendSecurityHeaders');
        $filename = $method->getFileName();
        $start = $method->getStartLine();
        $end = $method->getEndLine();

        $lines = array_slice(file($filename), $start - 1, $end - $start + 1);
        $source = implode('', $lines);

        expect($source)->not->toContain('X-XSS-Protection');
    });
});
