<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Data\LinkData;

describe('LinkData', function () {
    it('implements Arrayable', function () {
        $link = new LinkData(url: 'https://example.com', title: 'Example');
        expect($link)->toBeInstanceOf(Arrayable::class);
    });

    it('constructs with required and optional properties', function () {
        $link = new LinkData(url: 'https://example.com', title: 'Click me', target: '_blank');

        expect($link->url)->toBe('https://example.com');
        expect($link->title)->toBe('Click me');
        expect($link->target)->toBe('_blank');
    });

    it('defaults target to empty string', function () {
        $link = new LinkData(url: 'https://example.com', title: 'Click me');

        expect($link->target)->toBe('');
    });

    it('converts to array', function () {
        $link = new LinkData(url: 'https://example.com', title: 'Click me', target: '_blank');

        expect($link->toArray())->toBe([
            'url' => 'https://example.com',
            'title' => 'Click me',
            'target' => '_blank',
        ]);
    });

    describe('fromAcf', function () {
        it('creates from a valid ACF link array', function () {
            $link = LinkData::fromAcf([
                'url' => 'https://example.com',
                'title' => 'Example',
                'target' => '_blank',
            ]);

            expect($link)->toBeInstanceOf(LinkData::class);
            expect($link->url)->toBe('https://example.com');
            expect($link->title)->toBe('Example');
            expect($link->target)->toBe('_blank');
        });

        it('returns null for null input', function () {
            expect(LinkData::fromAcf(null))->toBeNull();
        });

        it('returns null when url is empty', function () {
            expect(LinkData::fromAcf(['url' => '', 'title' => 'No URL']))->toBeNull();
        });

        it('returns null when url is missing', function () {
            expect(LinkData::fromAcf(['title' => 'No URL key']))->toBeNull();
        });

        it('defaults title and target when missing from ACF array', function () {
            $link = LinkData::fromAcf(['url' => 'https://example.com']);

            expect($link)->toBeInstanceOf(LinkData::class);
            expect($link->title)->toBe('');
            expect($link->target)->toBe('');
        });
    });
});
