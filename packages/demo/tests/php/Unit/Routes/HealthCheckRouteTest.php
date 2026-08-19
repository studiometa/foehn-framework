<?php

declare(strict_types=1);

use Demo\Routes\HealthCheckRoute;
use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;

describe('HealthCheckRoute', function () {
    it('implements RewriteHandlerInterface', function () {
        expect(is_subclass_of(HealthCheckRoute::class, RewriteHandlerInterface::class))->toBeTrue();
    });

    it('declares the URL it answers', function () {
        $attrs = new ReflectionClass(HealthCheckRoute::class)->getAttributes(AsRewriteRule::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->regex)->toBe('^_health/?$');
        expect($attr->query)->toBe('index.php?foehn_route=health');
    });

    it('registers the query variable that makes it reachable', function () {
        // WordPress discards a query variable it does not know, so without this
        // the rewrite lands on a request the handler never sees.
        $attr = new ReflectionClass(HealthCheckRoute::class)->getAttributes(AsRewriteRule::class)[0]->newInstance();

        expect($attr->queryVars)->toBe(['foehn_route']);
    });

    it('matches before WordPress own rules', function () {
        $attr = new ReflectionClass(HealthCheckRoute::class)->getAttributes(AsRewriteRule::class)[0]->newInstance();

        expect($attr->after)->toBe('top');
    });
});
