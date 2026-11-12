<?php

declare(strict_types=1);

use Demo\Models\Photograph;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Image;

describe('Photograph', function () {
    it('is the Timber model of attachments', function () {
        $attrs = new ReflectionClass(Photograph::class)->getAttributes(AsTimberModel::class);

        expect(is_subclass_of(Photograph::class, Image::class))->toBeTrue();
        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->name)->toBe('attachment');
    });
});
