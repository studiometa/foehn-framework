<?php

declare(strict_types=1);

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Post;
use Timber\PostCollectionInterface;
use Timber\Site;
use Timber\User;

// Simple stubs to avoid WordPress dependency
class SiteStub extends Site
{
    public function __construct()
    {
        // Skip parent constructor which requires WordPress
    }
}

class PostStub extends Post
{
    public function __construct()
    {
        // Skip parent constructor which requires WordPress
        $this->post_type = 'post';
    }
}

class ProductStub extends Post
{
    public function __construct()
    {
        // Skip parent constructor which requires WordPress
        $this->post_type = 'product';
    }
}

/**
 * @implements PostCollectionInterface<Post>
 */
class PostCollectionStub implements PostCollectionInterface, \IteratorAggregate
{
    /** @param Post[] $posts */
    public function __construct(private array $posts = []) {}

    public function pagination(array $options = []): null { return null; }
    public function to_array(): array { return $this->posts; }
    public function count(): int { return count($this->posts); }
    public function offsetExists(mixed $offset): bool { return isset($this->posts[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->posts[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->posts[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->posts[$offset]); }
    public function getIterator(): \Traversable { return new \ArrayIterator($this->posts); }
}

beforeEach(function () {
    $this->site = new SiteStub();
});

describe('TemplateContext', function () {
    it('can be created with typed properties', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->post)->toBeNull();
        expect($context->posts)->toBeNull();
        expect($context->site)->toBeInstanceOf(Site::class);
        expect($context->user)->toBeNull();
    });

    it('can be created with extra data', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
            extra: ['custom_key' => 'custom_value'],
        );

        expect($context->site)->toBeInstanceOf(Site::class);
        expect($context->get('custom_key'))->toBe('custom_value');
    });

    it('returns typed post when casting to base class', function () {
        $post = new PostStub();
        $context = new TemplateContext(
            post: $post,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->post())->toBe($post);
        expect($context->post(Post::class))->toBe($post);
    });

    it('returns null when casting to incompatible class', function () {
        $post = new PostStub();
        $context = new TemplateContext(
            post: $post,
            posts: null,
            site: $this->site,
            user: null,
        );

        // Post is not an instance of User, so should return null
        expect($context->post(User::class))->toBeNull();
    });

    it('returns null when post is null', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->post())->toBeNull();
        expect($context->post(Post::class))->toBeNull();
    });

    it('returns posts collection without class check', function () {
        $posts = new PostCollectionStub([new PostStub(), new PostStub()]);
        $context = new TemplateContext(
            post: null,
            posts: $posts,
            site: $this->site,
            user: null,
        );

        expect($context->posts())->toBe($posts);
    });

    it('returns typed posts collection when class matches', function () {
        $posts = new PostCollectionStub([new ProductStub(), new ProductStub()]);
        $context = new TemplateContext(
            post: null,
            posts: $posts,
            site: $this->site,
            user: null,
        );

        expect($context->posts(ProductStub::class))->toBe($posts);
    });

    it('returns null when posts class does not match', function () {
        $posts = new PostCollectionStub([new PostStub(), new PostStub()]);
        $context = new TemplateContext(
            post: null,
            posts: $posts,
            site: $this->site,
            user: null,
        );

        expect($context->posts(ProductStub::class))->toBeNull();
    });

    it('returns null when posts is null', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->posts())->toBeNull();
        expect($context->posts(ProductStub::class))->toBeNull();
    });

    it('returns empty collection when checking class on empty collection', function () {
        $posts = new PostCollectionStub([]);
        $context = new TemplateContext(
            post: null,
            posts: $posts,
            site: $this->site,
            user: null,
        );

        // Empty collection returns itself (no first post to check)
        expect($context->posts(ProductStub::class))->toBe($posts);
    });

    it('supports get() for dynamic keys', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
            extra: ['foo' => 'bar', 'baz' => 123],
        );

        expect($context->get('foo'))->toBe('bar');
        expect($context->get('baz'))->toBe(123);
        expect($context->get('missing'))->toBeNull();
        expect($context->get('missing', 'default'))->toBe('default');
    });

    it('supports get() for typed properties', function () {
        $post = new PostStub();
        $context = new TemplateContext(
            post: $post,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->get('post'))->toBe($post);
        expect($context->get('posts'))->toBeNull();
        expect($context->get('site'))->toBe($this->site);
        expect($context->get('user'))->toBeNull();
        expect($context->get('posts', 'default'))->toBe('default');
    });

    it('supports has() for checking keys', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
            extra: ['foo' => 'bar'],
        );

        expect($context->has('foo'))->toBeTrue();
        expect($context->has('missing'))->toBeFalse();
    });

    it('supports has() for typed properties', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->has('post'))->toBeTrue();
        expect($context->has('posts'))->toBeTrue();
        expect($context->has('site'))->toBeTrue();
        expect($context->has('user'))->toBeTrue();
    });

    it('supports immutable with()', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        $newContext = $context->with('foo', 'bar');

        expect($context->has('foo'))->toBeFalse();
        expect($newContext->has('foo'))->toBeTrue();
        expect($newContext->get('foo'))->toBe('bar');
    });

    it('supports chained with() calls', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        $newContext = $context
            ->with('foo', 'bar')
            ->with('baz', 123)
            ->with('qux', true);

        expect($newContext->get('foo'))->toBe('bar');
        expect($newContext->get('baz'))->toBe(123);
        expect($newContext->get('qux'))->toBeTrue();
    });

    it('supports merge() with array', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
            extra: ['existing' => 'value'],
        );

        $newContext = $context->merge(['foo' => 'bar', 'baz' => 123]);

        expect($context->has('foo'))->toBeFalse();
        expect($newContext->get('existing'))->toBe('value');
        expect($newContext->get('foo'))->toBe('bar');
        expect($newContext->get('baz'))->toBe(123);
    });

    it('supports merge() with Arrayable', function () {
        $dto = new class implements Arrayable {
            use HasToArray;
            public string $foo = 'bar';
            public int $baz = 123;
        };

        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        $newContext = $context->merge($dto);

        expect($newContext->get('foo'))->toBe('bar');
        expect($newContext->get('baz'))->toBe(123);
    });

    it('supports withDto() for typed DTO storage', function () {
        $dto = new class implements Arrayable {
            use HasToArray;
            public string $foo = 'bar';
            public int $baz = 123;
        };

        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        $newContext = $context->withDto($dto);

        // Properties are flattened
        expect($newContext->get('foo'))->toBe('bar');
        expect($newContext->get('baz'))->toBe(123);

        // DTO is retrievable typed
        expect($newContext->dto($dto::class))->toBe($dto);
    });

    it('returns null for unknown DTO class', function () {
        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        expect($context->dto('NonExistent\\Class'))->toBeNull();
    });

    it('converts to array', function () {
        $post = new PostStub();
        $context = new TemplateContext(
            post: $post,
            posts: null,
            site: $this->site,
            user: null,
            extra: ['foo' => 'bar'],
        );

        $array = $context->toArray();

        expect($array)->toBeArray();
        expect($array['post'])->toBe($post);
        expect($array['posts'])->toBeNull();
        expect($array['site'])->toBe($this->site);
        expect($array['user'])->toBeNull();
        expect($array['foo'])->toBe('bar');
    });

    it('excludes __dtos from toArray()', function () {
        $dto = new class implements Arrayable {
            use HasToArray;
            public string $foo = 'bar';
        };

        $context = new TemplateContext(
            post: null,
            posts: null,
            site: $this->site,
            user: null,
        );

        $newContext = $context->withDto($dto);
        $array = $newContext->toArray();

        expect($array)->not->toHaveKey('__dtos');
        expect($array)->toHaveKey('foo');
    });

    describe('ArrayAccess', function () {
        it('supports offsetExists via has()', function () {
            $context = new TemplateContext(
                post: null,
                posts: null,
                site: $this->site,
                user: null,
                extra: ['foo' => 'bar'],
            );

            expect(isset($context['foo']))->toBeTrue();
            expect(isset($context['missing']))->toBeFalse();
        });

        it('supports offsetGet via get()', function () {
            $context = new TemplateContext(
                post: null,
                posts: null,
                site: $this->site,
                user: null,
                extra: ['foo' => 'bar'],
            );

            expect($context['foo'])->toBe('bar');
            expect($context['missing'])->toBeNull();
        });

        it('supports offsetGet for typed properties', function () {
            $post = new PostStub();
            $context = new TemplateContext(
                post: $post,
                posts: null,
                site: $this->site,
                user: null,
            );

            expect($context['post'])->toBe($post);
            expect($context['site'])->toBe($this->site);
        });

        it('supports offsetExists for typed properties', function () {
            $context = new TemplateContext(
                post: null,
                posts: null,
                site: $this->site,
                user: null,
            );

            expect(isset($context['post']))->toBeTrue();
            expect(isset($context['site']))->toBeTrue();
        });

        it('throws on offsetSet', function () {
            $context = new TemplateContext(
                post: null,
                posts: null,
                site: $this->site,
                user: null,
            );

            expect(fn () => $context['foo'] = 'bar')
                ->toThrow(BadMethodCallException::class, 'immutable');
        });

        it('throws on offsetUnset', function () {
            $context = new TemplateContext(
                post: null,
                posts: null,
                site: $this->site,
                user: null,
                extra: ['foo' => 'bar'],
            );

            expect(function () use ($context) {
                unset($context['foo']);
            })->toThrow(BadMethodCallException::class, 'immutable');
        });
    });
});
