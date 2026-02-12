<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Data\ImageData;

beforeEach(function () {
    wp_stub_reset();
});

describe('ImageData', function () {
    it('implements Arrayable', function () {
        $image = new ImageData(id: 1, src: '/img.jpg');
        expect($image)->toBeInstanceOf(Arrayable::class);
    });

    it('constructs with all properties', function () {
        $image = new ImageData(id: 42, src: '/photo.jpg', alt: 'A photo', width: 1920, height: 1080);

        expect($image->id)->toBe(42);
        expect($image->src)->toBe('/photo.jpg');
        expect($image->alt)->toBe('A photo');
        expect($image->width)->toBe(1920);
        expect($image->height)->toBe(1080);
    });

    it('defaults alt to empty string and dimensions to null', function () {
        $image = new ImageData(id: 1, src: '/img.jpg');

        expect($image->alt)->toBe('');
        expect($image->width)->toBeNull();
        expect($image->height)->toBeNull();
    });

    it('converts to array', function () {
        $image = new ImageData(id: 42, src: '/photo.jpg', alt: 'A photo', width: 1920, height: 1080);

        expect($image->toArray())->toBe([
            'id' => 42,
            'src' => '/photo.jpg',
            'alt' => 'A photo',
            'width' => 1920,
            'height' => 1080,
        ]);
    });

    it('converts to array with null dimensions', function () {
        $image = new ImageData(id: 1, src: '/img.jpg');

        expect($image->toArray())->toBe([
            'id' => 1,
            'src' => '/img.jpg',
            'alt' => '',
            'width' => null,
            'height' => null,
        ]);
    });

    describe('fromAttachmentId', function () {
        it('returns null for null id', function () {
            expect(ImageData::fromAttachmentId(null))->toBeNull();
        });

        it('returns null for zero id', function () {
            expect(ImageData::fromAttachmentId(0))->toBeNull();
        });

        it('returns null when attachment does not exist', function () {
            expect(ImageData::fromAttachmentId(999))->toBeNull();
        });

        it('creates from a valid attachment id', function () {
            $GLOBALS['wp_stub_attachments'][42] = [
                'url' => 'https://example.com/photo.jpg',
                'meta' => ['width' => 1920, 'height' => 1080],
            ];
            $GLOBALS['wp_stub_post_meta'][42]['_wp_attachment_image_alt'] = 'Alt text';

            $image = ImageData::fromAttachmentId(42);

            expect($image)->toBeInstanceOf(ImageData::class);
            expect($image->id)->toBe(42);
            expect($image->src)->toBe('https://example.com/photo.jpg');
            expect($image->alt)->toBe('Alt text');
            expect($image->width)->toBe(1920);
            expect($image->height)->toBe(1080);
        });

        it('defaults alt to empty string when no alt meta', function () {
            $GLOBALS['wp_stub_attachments'][10] = [
                'url' => 'https://example.com/img.jpg',
                'meta' => ['width' => 800, 'height' => 600],
            ];

            $image = ImageData::fromAttachmentId(10);

            expect($image)->toBeInstanceOf(ImageData::class);
            expect($image->alt)->toBe('');
        });

        it('handles missing metadata gracefully', function () {
            $GLOBALS['wp_stub_attachments'][15] = [
                'url' => 'https://example.com/img.jpg',
                // No 'meta' key → wp_get_attachment_metadata returns false
            ];

            $image = ImageData::fromAttachmentId(15);

            expect($image)->toBeInstanceOf(ImageData::class);
            expect($image->width)->toBeNull();
            expect($image->height)->toBeNull();
        });
    });
});
