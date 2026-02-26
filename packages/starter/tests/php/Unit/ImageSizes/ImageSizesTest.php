<?php

declare(strict_types=1);

use App\ImageSizes\CardImageSize;
use App\ImageSizes\HeroImageSize;
use Studiometa\Foehn\Attributes\AsImageSize;

describe('ImageSizes', function () {
    it('CardImageSize has correct dimensions', function () {
        $ref = new ReflectionClass(CardImageSize::class);
        $attrs = $ref->getAttributes(AsImageSize::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->width)->toBe(400);
        expect($attr->height)->toBe(300);
        expect($attr->crop)->toBeTrue();
    });

    it('HeroImageSize has correct dimensions', function () {
        $ref = new ReflectionClass(HeroImageSize::class);
        $attrs = $ref->getAttributes(AsImageSize::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->width)->toBe(1920);
        expect($attr->height)->toBe(1080);
        expect($attr->crop)->toBeTrue();
    });
});
