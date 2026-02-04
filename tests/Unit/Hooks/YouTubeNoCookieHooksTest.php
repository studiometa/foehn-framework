<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\YouTubeNoCookieHooks;

describe('YouTubeNoCookieHooks', function () {
    it('replaces youtube.com/embed with youtube-nocookie.com/embed in content', function () {
        $hooks = new YouTubeNoCookieHooks();

        $content = '<iframe src="https://www.youtube.com/embed/abc123"></iframe>';
        $expected = '<iframe src="https://www.youtube-nocookie.com/embed/abc123"></iframe>';

        expect($hooks->replaceInContent($content))->toBe($expected);
    });

    it('replaces youtube.com/embed with youtube-nocookie.com/embed in ACF oEmbed', function () {
        $hooks = new YouTubeNoCookieHooks();

        $value = '<iframe src="https://www.youtube.com/embed/xyz789"></iframe>';
        $expected = '<iframe src="https://www.youtube-nocookie.com/embed/xyz789"></iframe>';

        expect($hooks->replaceInAcfOembed($value))->toBe($expected);
    });

    it('does not modify content without youtube embeds', function () {
        $hooks = new YouTubeNoCookieHooks();

        $content = '<p>Hello world</p>';

        expect($hooks->replaceInContent($content))->toBe($content);
    });

    it('does not modify already nocookie URLs', function () {
        $hooks = new YouTubeNoCookieHooks();

        $content = '<iframe src="https://www.youtube-nocookie.com/embed/abc123"></iframe>';

        expect($hooks->replaceInContent($content))->toBe($content);
    });

    it('handles multiple embeds in content', function () {
        $hooks = new YouTubeNoCookieHooks();

        $content = '<iframe src="https://www.youtube.com/embed/abc"></iframe>'
            . '<iframe src="https://www.youtube.com/embed/xyz"></iframe>';
        $expected = '<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'
            . '<iframe src="https://www.youtube-nocookie.com/embed/xyz"></iframe>';

        expect($hooks->replaceInContent($content))->toBe($expected);
    });

    it('has correct filter attributes', function () {
        $reflection = new ReflectionClass(YouTubeNoCookieHooks::class);

        $contentMethod = $reflection->getMethod('replaceInContent');
        $contentAttrs = $contentMethod->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($contentAttrs)->toHaveCount(1);
        expect($contentAttrs[0]->newInstance()->hook)->toBe('the_content');

        $acfMethod = $reflection->getMethod('replaceInAcfOembed');
        $acfAttrs = $acfMethod->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($acfAttrs)->toHaveCount(1);
        expect($acfAttrs[0]->newInstance()->hook)->toBe('acf/format_value/type=oembed');
    });
});
