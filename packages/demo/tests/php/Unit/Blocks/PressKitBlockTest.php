<?php

declare(strict_types=1);

use Demo\Blocks\PressKitBlock;

describe('PressKitBlock', function () {
    beforeEach(fn() => wp_stub_reset());

    it('offers PDF files only, with a file control', function () {
        $fileId = PressKitBlock::attributes()['fileId'];

        expect($fileId['control'])->toBe('file');
        expect($fileId['allowedTypes'])->toBe(['application/pdf']);
    });

    it('links the file and gives its size', function () {
        $GLOBALS['wp_stub_attachments'][12] = [
            'url' => 'https://foehn.test/press-kit.pdf',
            'meta' => ['filesize' => 2048],
        ];

        $context = new PressKitBlock(createFakeViewEngine())->compose(
            ['fileId' => 12, 'label' => 'Press kit'],
            '',
            new WP_Block([]),
        );

        expect($context['url'])->toBe('https://foehn.test/press-kit.pdf');
        expect($context['label'])->toBe('Press kit');
        expect($context['size'])->not->toBeNull();
    });

    it('renders nothing to link when no file is chosen', function () {
        $context = new PressKitBlock(createFakeViewEngine())->compose([], '', new WP_Block([]));

        expect($context['url'])->toBeNull();
    });
});
