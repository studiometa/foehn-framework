<?php

declare(strict_types=1);

use Demo\Patterns\CommissionPattern;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

describe('CommissionPattern', function () {
    beforeEach(fn() => wp_stub_reset());

    it('is a block pattern with a template named after it', function () {
        $attr = new ReflectionClass(CommissionPattern::class)->getAttributes(AsBlockPattern::class)[0]->newInstance();

        expect(is_subclass_of(CommissionPattern::class, BlockPatternInterface::class))->toBeTrue();
        expect($attr->name)->toBe('theme/commission');
        expect($attr->getTemplatePath())->toBe('patterns/commission');
        expect(dirname(__DIR__, 4) . '/theme/templates/patterns/commission.twig')->toBeFile();
    });

    it('gives its template the contact address', function () {
        $GLOBALS['wp_stub_options']['demo_contact_email'] = 'studio@foehn.test';

        expect(new CommissionPattern()->compose())->toBe(['email' => 'studio@foehn.test']);
    });
});
