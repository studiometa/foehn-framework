<?php

declare(strict_types=1);

use Studiometa\WPTempest\Helpers\VideoEmbed;

describe('VideoEmbed', function () {
    describe('detectPlatform()', function () {
        it('detects YouTube from youtube.com/watch URL', function () {
            expect(VideoEmbed::detectPlatform('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
                ->toBe('youtube');
        });

        it('detects YouTube from youtu.be URL', function () {
            expect(VideoEmbed::detectPlatform('https://youtu.be/dQw4w9WgXcQ'))
                ->toBe('youtube');
        });

        it('detects YouTube from embed URL', function () {
            expect(VideoEmbed::detectPlatform('https://www.youtube.com/embed/dQw4w9WgXcQ'))
                ->toBe('youtube');
        });

        it('detects YouTube from /v/ URL', function () {
            expect(VideoEmbed::detectPlatform('https://www.youtube.com/v/dQw4w9WgXcQ'))
                ->toBe('youtube');
        });

        it('detects YouTube from youtube-nocookie.com URL', function () {
            expect(VideoEmbed::detectPlatform('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'))
                ->toBe('youtube');
        });

        it('detects Vimeo from vimeo.com URL', function () {
            expect(VideoEmbed::detectPlatform('https://vimeo.com/123456789'))
                ->toBe('vimeo');
        });

        it('detects Vimeo from channel URL', function () {
            expect(VideoEmbed::detectPlatform('https://vimeo.com/channels/staffpicks/123456789'))
                ->toBe('vimeo');
        });

        it('detects Vimeo from player URL', function () {
            expect(VideoEmbed::detectPlatform('https://player.vimeo.com/video/123456789'))
                ->toBe('vimeo');
        });

        it('detects Vimeo from groups URL', function () {
            expect(VideoEmbed::detectPlatform('https://vimeo.com/groups/motion/videos/123456789'))
                ->toBe('vimeo');
        });

        it('returns null for unsupported URLs', function () {
            expect(VideoEmbed::detectPlatform('https://example.com/video'))
                ->toBeNull();
            expect(VideoEmbed::detectPlatform('https://dailymotion.com/video/123'))
                ->toBeNull();
            expect(VideoEmbed::detectPlatform('not a url'))
                ->toBeNull();
        });
    });

    describe('extractId()', function () {
        it('extracts ID from youtube.com/watch URL', function () {
            expect(VideoEmbed::extractId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
                ->toBe('dQw4w9WgXcQ');
        });

        it('extracts ID from youtube.com/watch URL with extra params', function () {
            expect(VideoEmbed::extractId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120&list=PLtest'))
                ->toBe('dQw4w9WgXcQ');
        });

        it('extracts ID from youtu.be URL', function () {
            expect(VideoEmbed::extractId('https://youtu.be/dQw4w9WgXcQ'))
                ->toBe('dQw4w9WgXcQ');
        });

        it('extracts ID from youtube.com/embed URL', function () {
            expect(VideoEmbed::extractId('https://www.youtube.com/embed/dQw4w9WgXcQ'))
                ->toBe('dQw4w9WgXcQ');
        });

        it('extracts ID from youtube.com/v/ URL', function () {
            expect(VideoEmbed::extractId('https://www.youtube.com/v/dQw4w9WgXcQ'))
                ->toBe('dQw4w9WgXcQ');
        });

        it('extracts ID from vimeo.com URL', function () {
            expect(VideoEmbed::extractId('https://vimeo.com/123456789'))
                ->toBe('123456789');
        });

        it('extracts ID from vimeo channel URL', function () {
            expect(VideoEmbed::extractId('https://vimeo.com/channels/staffpicks/123456789'))
                ->toBe('123456789');
        });

        it('extracts ID from player.vimeo.com URL', function () {
            expect(VideoEmbed::extractId('https://player.vimeo.com/video/123456789'))
                ->toBe('123456789');
        });

        it('extracts ID from vimeo groups URL', function () {
            expect(VideoEmbed::extractId('https://vimeo.com/groups/motion/videos/123456789'))
                ->toBe('123456789');
        });

        it('returns null for unsupported URLs', function () {
            expect(VideoEmbed::extractId('https://example.com/video'))
                ->toBeNull();
        });
    });

    describe('isSupported()', function () {
        it('returns true for supported YouTube URLs', function () {
            expect(VideoEmbed::isSupported('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
                ->toBeTrue();
            expect(VideoEmbed::isSupported('https://youtu.be/dQw4w9WgXcQ'))
                ->toBeTrue();
        });

        it('returns true for supported Vimeo URLs', function () {
            expect(VideoEmbed::isSupported('https://vimeo.com/123456789'))
                ->toBeTrue();
            expect(VideoEmbed::isSupported('https://player.vimeo.com/video/123456789'))
                ->toBeTrue();
        });

        it('returns false for unsupported URLs', function () {
            expect(VideoEmbed::isSupported('https://example.com/video'))
                ->toBeFalse();
            expect(VideoEmbed::isSupported(''))
                ->toBeFalse();
        });
    });

    describe('embedUrl()', function () {
        describe('YouTube', function () {
            it('generates nocookie embed URL by default', function () {
                $url = VideoEmbed::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

                expect($url)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
            });

            it('generates regular embed URL when nocookie is false', function () {
                $url = VideoEmbed::embedUrl(
                    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ['nocookie' => false],
                );

                expect($url)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
            });

            it('adds autoplay parameter', function () {
                $url = VideoEmbed::embedUrl(
                    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ['autoplay' => true],
                );

                expect($url)->toContain('autoplay=1');
                // Autoplay implies mute for YouTube
                expect($url)->toContain('mute=1');
            });

            it('adds loop parameter with playlist', function () {
                $url = VideoEmbed::embedUrl(
                    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ['loop' => true],
                );

                expect($url)->toContain('loop=1');
                expect($url)->toContain('playlist=dQw4w9WgXcQ');
            });

            it('adds mute parameter independently', function () {
                $url = VideoEmbed::embedUrl(
                    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ['muted' => true],
                );

                expect($url)->toContain('mute=1');
                expect($url)->not->toContain('autoplay');
            });

            it('converts timestamp ?t=120 to ?start=120', function () {
                $url = VideoEmbed::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120');

                expect($url)->toContain('start=120');
            });

            it('converts timestamp ?t=2m30s to ?start=150', function () {
                $url = VideoEmbed::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=2m30s');

                expect($url)->toContain('start=150');
            });

            it('converts timestamp ?t=1h2m30s to ?start=3750', function () {
                $url = VideoEmbed::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1h2m30s');

                expect($url)->toContain('start=3750');
            });

            it('works with youtu.be URLs', function () {
                $url = VideoEmbed::embedUrl('https://youtu.be/dQw4w9WgXcQ');

                expect($url)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
            });

            it('works with URLs without https://', function () {
                $url = VideoEmbed::embedUrl('youtube.com/watch?v=dQw4w9WgXcQ');

                expect($url)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
            });
        });

        describe('Vimeo', function () {
            it('generates player embed URL with dnt parameter', function () {
                $url = VideoEmbed::embedUrl('https://vimeo.com/123456789');

                expect($url)->toBe('https://player.vimeo.com/video/123456789?dnt=1');
            });

            it('adds autoplay parameter', function () {
                $url = VideoEmbed::embedUrl(
                    'https://vimeo.com/123456789',
                    ['autoplay' => true],
                );

                expect($url)->toContain('autoplay=1');
                // Autoplay implies muted
                expect($url)->toContain('muted=1');
            });

            it('adds loop parameter', function () {
                $url = VideoEmbed::embedUrl(
                    'https://vimeo.com/123456789',
                    ['loop' => true],
                );

                expect($url)->toContain('loop=1');
            });

            it('adds muted parameter independently', function () {
                $url = VideoEmbed::embedUrl(
                    'https://vimeo.com/123456789',
                    ['muted' => true],
                );

                expect($url)->toContain('muted=1');
                expect($url)->not->toContain('autoplay');
            });

            it('works with channel URLs', function () {
                $url = VideoEmbed::embedUrl('https://vimeo.com/channels/staffpicks/123456789');

                expect($url)->toBe('https://player.vimeo.com/video/123456789?dnt=1');
            });

            it('works with player URLs', function () {
                $url = VideoEmbed::embedUrl('https://player.vimeo.com/video/123456789');

                expect($url)->toBe('https://player.vimeo.com/video/123456789?dnt=1');
            });
        });

        describe('unsupported URLs', function () {
            it('returns null for unsupported URLs', function () {
                expect(VideoEmbed::embedUrl('https://example.com/video'))
                    ->toBeNull();
            });

            it('returns null for empty string', function () {
                expect(VideoEmbed::embedUrl(''))
                    ->toBeNull();
            });

            it('returns null for invalid URLs', function () {
                expect(VideoEmbed::embedUrl('not a url'))
                    ->toBeNull();
            });
        });

        describe('combined options', function () {
            it('combines multiple YouTube options', function () {
                $url = VideoEmbed::embedUrl(
                    'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30',
                    ['autoplay' => true, 'loop' => true],
                );

                expect($url)->toContain('autoplay=1');
                expect($url)->toContain('loop=1');
                expect($url)->toContain('mute=1');
                expect($url)->toContain('playlist=dQw4w9WgXcQ');
                expect($url)->toContain('start=30');
            });

            it('combines multiple Vimeo options', function () {
                $url = VideoEmbed::embedUrl(
                    'https://vimeo.com/123456789',
                    ['autoplay' => true, 'loop' => true],
                );

                expect($url)->toContain('autoplay=1');
                expect($url)->toContain('loop=1');
                expect($url)->toContain('muted=1');
                expect($url)->toContain('dnt=1');
            });

            it('allows explicit muted=false even with autoplay', function () {
                $url = VideoEmbed::embedUrl(
                    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ['autoplay' => true, 'muted' => false],
                );

                expect($url)->toContain('autoplay=1');
                expect($url)->not->toContain('mute=1');
            });
        });
    });
});
