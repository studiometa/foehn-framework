<?php

declare(strict_types=1);

use Demo\Blocks\ContactSheetBlock;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;

function createContactSheet(): ContactSheetBlock
{
    $transformer = new class implements ImageTransformer {
        public function url(string $src, array $transform): string
        {
            return $src . '?w=' . $transform['w'];
        }

        public function forget(string $src): void {}
    };

    return new ContactSheetBlock(createFakeViewEngine(), $transformer);
}

describe('ContactSheetBlock', function () {
    beforeEach(fn() => wp_stub_reset());

    it('is an interactive block', function () {
        $attr = new ReflectionClass(ContactSheetBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();

        expect(is_subclass_of(ContactSheetBlock::class, InteractiveBlockInterface::class))->toBeTrue();
        expect($attr->name)->toBe('theme/contact-sheet');
        expect($attr->interactivity)->toBeTrue();
    });

    it('stores its photographs with a gallery control', function () {
        $imageIds = ContactSheetBlock::attributes()['imageIds'];

        expect($imageIds['control'])->toBe('gallery');
        expect($imageIds['type'])->toBe('array');
        expect($imageIds['items'])->toBe(['type' => 'integer']);
    });

    it('starts closed on the first photograph, and skips a missing one', function () {
        $GLOBALS['wp_stub_attachments'][7] = ['url' => 'https://foehn.test/a.jpg'];
        $GLOBALS['wp_stub_post_meta'][7]['_wp_attachment_image_alt'] = 'A corridor';

        $context = createContactSheet()->initialContext(['imageIds' => [7, 99]]);

        expect($context['isOpen'])->toBeFalse();
        expect($context['current'])->toBe(0);
        expect($context['photographs'])->toBe([
            ['id' => 7, 'src' => 'https://foehn.test/a.jpg?w=1600', 'alt' => 'A corridor'],
        ]);
    });

    it('shares its labels across every sheet through the namespace state', function () {
        expect(array_keys(ContactSheetBlock::initialState()))->toBe(['closeLabel', 'previousLabel', 'nextLabel']);
    });
});
