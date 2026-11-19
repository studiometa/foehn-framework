<?php

declare(strict_types=1);

use Demo\JsonLd\ProjectJsonLd;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;

describe('ProjectJsonLd', function () {
    it('gives the nodes of the project pages', function () {
        $attributes = new ReflectionClass(ProjectJsonLd::class)->getAttributes(AsJsonLd::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->getPostTypes())->toBe(['project']);
        expect(is_subclass_of(ProjectJsonLd::class, JsonLdProviderInterface::class))->toBeTrue();
    });
});
