<?php

declare(strict_types=1);

use Studiometa\Foehn\Timber\TimberRepository;

describe('TimberRepository', function () {
    it('is readonly', function () {
        expect(TimberRepository::class)->toBeReadonly();
    });

    it('can be instantiated', function () {
        $repository = new TimberRepository();

        expect($repository)->toBeInstanceOf(TimberRepository::class);
    });
});
