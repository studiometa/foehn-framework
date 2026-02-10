<?php

declare(strict_types=1);

use Studiometa\Foehn\Views\Twig\QueryExtension;

describe('QueryExtension', function () {
    beforeEach(function () {
        // Reset superglobals
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/';
        $this->extension = new QueryExtension();
    });

    afterEach(function () {
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/';
    });

    it('has a name', function () {
        expect($this->extension->getName())->toBe('foehn_query');
    });

    it('registers functions', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('query_get');
        expect($names)->toContain('query_has');
        expect($names)->toContain('query_contains');
        expect($names)->toContain('query_all');
        expect($names)->toContain('query_url');
        expect($names)->toContain('query_url_without');
        expect($names)->toContain('query_url_toggle');
        expect($names)->toContain('query_url_clear');
        expect($names)->toContain('query_hidden_inputs');
    });

    describe('get', function () {
        it('returns parameter value', function () {
            $_GET['category'] = 'news';

            expect($this->extension->get('category'))->toBe('news');
        });

        it('returns default for missing parameter', function () {
            expect($this->extension->get('category'))->toBeNull();
            expect($this->extension->get('category', 'default'))->toBe('default');
        });

        it('returns default for empty string', function () {
            $_GET['category'] = '';

            expect($this->extension->get('category', 'default'))->toBe('default');
        });

        it('returns default for empty array', function () {
            $_GET['category'] = [];

            expect($this->extension->get('category', 'default'))->toBe('default');
        });

        it('returns array values', function () {
            $_GET['tags'] = ['php', 'js'];

            expect($this->extension->get('tags'))->toBe(['php', 'js']);
        });
    });

    describe('has', function () {
        it('returns true for existing parameter', function () {
            $_GET['category'] = 'news';

            expect($this->extension->has('category'))->toBeTrue();
        });

        it('returns false for missing parameter', function () {
            expect($this->extension->has('category'))->toBeFalse();
        });

        it('returns true when value matches', function () {
            $_GET['category'] = 'news';

            expect($this->extension->has('category', 'news'))->toBeTrue();
        });

        it('returns false when value does not match', function () {
            $_GET['category'] = 'news';

            expect($this->extension->has('category', 'blog'))->toBeFalse();
        });
    });

    describe('contains', function () {
        it('returns true for value in array', function () {
            $_GET['tags'] = ['php', 'js'];

            expect($this->extension->contains('tags', 'php'))->toBeTrue();
            expect($this->extension->contains('tags', 'js'))->toBeTrue();
        });

        it('returns false for value not in array', function () {
            $_GET['tags'] = ['php', 'js'];

            expect($this->extension->contains('tags', 'python'))->toBeFalse();
        });

        it('works with scalar values', function () {
            $_GET['category'] = 'news';

            expect($this->extension->contains('category', 'news'))->toBeTrue();
            expect($this->extension->contains('category', 'blog'))->toBeFalse();
        });

        it('uses string comparison', function () {
            $_GET['page'] = '5';

            expect($this->extension->contains('page', 5))->toBeTrue();
            expect($this->extension->contains('page', '5'))->toBeTrue();
        });

        it('returns false for missing parameter', function () {
            expect($this->extension->contains('tags', 'php'))->toBeFalse();
        });
    });

    describe('all', function () {
        it('returns all non-empty parameters', function () {
            $_GET = [
                'category' => 'news',
                'page' => '2',
                'empty' => '',
                'emptyArray' => [],
            ];

            expect($this->extension->all())->toBe([
                'category' => 'news',
                'page' => '2',
            ]);
        });

        it('returns empty array when no parameters', function () {
            expect($this->extension->all())->toBe([]);
        });
    });

    describe('url', function () {
        it('adds parameters to current URL', function () {
            $_SERVER['REQUEST_URI'] = '/blog';

            $result = $this->extension->url(['category' => 'news']);

            expect($result)->toContain('/blog');
            expect($result)->toContain('category=news');
        });

        it('adds multiple parameters', function () {
            $_SERVER['REQUEST_URI'] = '/blog';

            $result = $this->extension->url(['category' => 'news', 'page' => 2]);

            expect($result)->toContain('category=news');
            expect($result)->toContain('page=2');
        });
    });

    describe('urlWithout', function () {
        it('removes single parameter', function () {
            $_SERVER['REQUEST_URI'] = '/blog?category=news&page=2';

            $result = $this->extension->urlWithout('category');

            expect($result)->not->toContain('category');
            expect($result)->toContain('page=2');
        });

        it('removes multiple parameters', function () {
            $_SERVER['REQUEST_URI'] = '/blog?category=news&page=2&sort=date';

            $result = $this->extension->urlWithout(['category', 'page']);

            expect($result)->not->toContain('category');
            expect($result)->not->toContain('page');
            expect($result)->toContain('sort=date');
        });
    });

    describe('urlToggle', function () {
        it('adds value when not present', function () {
            $_SERVER['REQUEST_URI'] = '/blog';

            $result = $this->extension->urlToggle('tags', 'php');

            expect($result)->toContain('tags');
            expect($result)->toContain('php');
        });

        it('removes value when present as scalar', function () {
            $_GET['tags'] = 'php';
            $_SERVER['REQUEST_URI'] = '/blog?tags=php';

            $result = $this->extension->urlToggle('tags', 'php');

            expect($result)->not->toContain('tags');
        });

        it('removes value from array', function () {
            $_GET['tags'] = ['php', 'js'];
            $_SERVER['REQUEST_URI'] = '/blog?tags[]=php&tags[]=js';

            $result = $this->extension->urlToggle('tags', 'php');

            expect($result)->toContain('tags');
            expect($result)->toContain('js');
        });

        it('adds value to existing array', function () {
            $_GET['tags'] = ['php'];
            $_SERVER['REQUEST_URI'] = '/blog?tags[]=php';

            $result = $this->extension->urlToggle('tags', 'js');

            expect($result)->toContain('tags');
            expect($result)->toContain('php');
            expect($result)->toContain('js');
        });

        it('adds value to existing scalar', function () {
            $_GET['tags'] = 'php';
            $_SERVER['REQUEST_URI'] = '/blog?tags=php';

            $result = $this->extension->urlToggle('tags', 'js');

            expect($result)->toContain('tags');
            expect($result)->toContain('php');
            expect($result)->toContain('js');
        });
    });

    describe('urlClear', function () {
        it('removes all query parameters', function () {
            $_SERVER['REQUEST_URI'] = '/blog?category=news&page=2';

            $result = $this->extension->urlClear();

            expect($result)->toBe('/blog');
        });

        it('handles URL without query string', function () {
            $_SERVER['REQUEST_URI'] = '/blog';

            $result = $this->extension->urlClear();

            expect($result)->toBe('/blog');
        });

        it('returns root for empty URI', function () {
            $_SERVER['REQUEST_URI'] = '';

            $result = $this->extension->urlClear();

            expect($result)->toBe('/');
        });
    });

    describe('hiddenInputs', function () {
        it('generates hidden inputs for parameters', function () {
            $_GET = [
                'category' => 'news',
                'page' => '2',
            ];

            $result = $this->extension->hiddenInputs();

            expect($result)->toContain('<input type="hidden" name="category" value="news">');
            expect($result)->toContain('<input type="hidden" name="page" value="2">');
        });

        it('handles array parameters', function () {
            $_GET = [
                'tags' => ['php', 'js'],
            ];

            $result = $this->extension->hiddenInputs();

            expect($result)->toContain('<input type="hidden" name="tags[]" value="php">');
            expect($result)->toContain('<input type="hidden" name="tags[]" value="js">');
        });

        it('excludes specified parameters', function () {
            $_GET = [
                'category' => 'news',
                'page' => '2',
                's' => 'search term',
            ];

            $result = $this->extension->hiddenInputs(['s']);

            expect($result)->toContain('category');
            expect($result)->toContain('page');
            expect($result)->not->toContain('name="s"');
        });

        it('escapes HTML entities', function () {
            $_GET = [
                'query' => '<script>alert("xss")</script>',
            ];

            $result = $this->extension->hiddenInputs();

            expect($result)->not->toContain('<script>');
            expect($result)->toContain('&lt;script&gt;');
        });

        it('returns empty string for empty parameters', function () {
            $result = $this->extension->hiddenInputs();

            expect($result)->toBe('');
        });
    });
});
