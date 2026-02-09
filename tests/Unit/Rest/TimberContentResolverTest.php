<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ContentResolverInterface;
use Studiometa\Foehn\Rest\TimberContentResolver;

describe('TimberContentResolver', function () {
    it('implements ContentResolverInterface', function () {
        $resolver = new TimberContentResolver();

        expect($resolver)->toBeInstanceOf(ContentResolverInterface::class);
    });

    it('is readonly', function () {
        expect(TimberContentResolver::class)->toBeReadonly();
    });
});
