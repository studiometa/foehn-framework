<?php

declare(strict_types=1);

use Studiometa\WPTempest\Views\Twig\VideoEmbedExtension;

describe('VideoEmbedExtension', function () {
    beforeEach(function () {
        $this->extension = new VideoEmbedExtension();
    });

    it('has a name', function () {
        expect($this->extension->getName())->toBe('video_embed');
    });

    it('registers functions', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('video_embed');
        expect($names)->toContain('video_id');
        expect($names)->toContain('video_platform');
        expect($names)->toContain('video_is_supported');
    });

    it('registers filters', function () {
        $filters = $this->extension->getFilters();
        $names = array_map(fn($f) => $f->getName(), $filters);

        expect($names)->toContain('video_embed');
        expect($names)->toContain('video_id');
        expect($names)->toContain('video_platform');
        expect($names)->toContain('video_is_supported');
    });

    it('functions call VideoEmbed methods', function () {
        $functions = $this->extension->getFunctions();

        foreach ($functions as $function) {
            $callable = $function->getCallable();
            expect($callable[0])->toBe('Studiometa\\WPTempest\\Helpers\\VideoEmbed');
        }
    });

    it('filters call VideoEmbed methods', function () {
        $filters = $this->extension->getFilters();

        foreach ($filters as $filter) {
            $callable = $filter->getCallable();
            expect($callable[0])->toBe('Studiometa\\WPTempest\\Helpers\\VideoEmbed');
        }
    });
});
