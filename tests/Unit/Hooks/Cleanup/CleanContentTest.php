<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\CleanContent;

describe('CleanContent', function () {
    it('removes empty paragraphs with &nbsp;', function () {
        $hooks = new CleanContent();

        expect($hooks->cleanEmptyParagraphs('<p>&nbsp;</p>'))->toBe('');
    });

    it('removes empty paragraphs with whitespace', function () {
        $hooks = new CleanContent();

        expect($hooks->cleanEmptyParagraphs('<p> </p>'))->toBe('');
        expect($hooks->cleanEmptyParagraphs('<p>  &nbsp;  </p>'))->toBe('');
    });

    it('preserves paragraphs with content', function () {
        $hooks = new CleanContent();

        expect($hooks->cleanEmptyParagraphs('<p>Hello</p>'))->toBe('<p>Hello</p>');
    });

    it('removes only empty paragraphs from mixed content', function () {
        $hooks = new CleanContent();

        expect($hooks->cleanEmptyParagraphs('<p>Hello</p><p>&nbsp;</p><p>World</p>'))->toBe('<p>Hello</p><p>World</p>');
    });

    it('removes archive title prefix', function () {
        $hooks = new CleanContent();

        expect($hooks->cleanArchiveTitlePrefix('Category: News'))->toBe('News');
        expect($hooks->cleanArchiveTitlePrefix('Tag: PHP'))->toBe('PHP');
        expect($hooks->cleanArchiveTitlePrefix('Archives: 2024'))->toBe('2024');
    });

    it('keeps titles without prefix', function () {
        $hooks = new CleanContent();

        expect($hooks->cleanArchiveTitlePrefix('Simple Title'))->toBe('Simple Title');
    });

    it('has correct filter attributes', function () {
        $reflection = new ReflectionClass(CleanContent::class);

        $contentMethod = $reflection->getMethod('cleanEmptyParagraphs');
        $contentAttrs = $contentMethod->getAttributes(\Studiometa\Foehn\Attributes\AsFilter::class);

        expect($contentAttrs)->toHaveCount(1);
        expect($contentAttrs[0]->newInstance()->hook)->toBe('the_content');
        expect($contentAttrs[0]->newInstance()->priority)->toBe(20);

        $archiveMethod = $reflection->getMethod('cleanArchiveTitlePrefix');
        $archiveAttrs = $archiveMethod->getAttributes(\Studiometa\Foehn\Attributes\AsFilter::class);

        expect($archiveAttrs)->toHaveCount(1);
        expect($archiveAttrs[0]->newInstance()->hook)->toBe('get_the_archive_title');
    });
});
