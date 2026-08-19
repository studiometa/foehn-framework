<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsRewriteRule;

describe('AsRewriteRule', function () {
    it('needs a pattern and what it rewrites to', function () {
        $attribute = new AsRewriteRule(regex: '^webhook/?$', query: 'index.php?foehn_route=hook');

        expect($attribute->regex)->toBe('^webhook/?$');
        expect($attribute->query)->toBe('index.php?foehn_route=hook');
        expect($attribute->queryVars)->toBe([]);
    });

    it('matches before WordPress own rules by default', function () {
        // Which is what a webhook wants: a rule at the bottom is reached only
        // after every built-in pattern has failed.
        expect(new AsRewriteRule(regex: '^x/?$', query: 'index.php')->after)->toBe('top');
    });

    it('can be instantiated with every parameter', function () {
        $attribute = new AsRewriteRule(
            regex: '^brochure/([^/]+)/?$',
            query: 'index.php?post_type=brochure&name=$matches[1]',
            queryVars: ['brochure'],
            after: 'bottom',
        );

        expect($attribute->queryVars)->toBe(['brochure']);
        expect($attribute->after)->toBe('bottom');
    });

    it('is readonly', function () {
        expect(AsRewriteRule::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $attributes = new ReflectionClass(AsRewriteRule::class)->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
