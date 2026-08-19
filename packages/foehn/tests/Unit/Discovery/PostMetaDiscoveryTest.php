<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Tests\Fixtures\PostMeta\AbstractBase;
use Tests\Fixtures\PostMeta\Article;
use Tests\Fixtures\PostMeta\Book;
use Tests\Fixtures\PostMeta\Gallery;
use Tests\Fixtures\PostMeta\Genre;
use Tests\Fixtures\PostMeta\InstanceSanitizer;
use Tests\Fixtures\PostMeta\InvalidObjectType;
use Tests\Fixtures\PostMeta\InvalidType;
use Tests\Fixtures\PostMeta\MissingSanitizer;
use Tests\Fixtures\PostMeta\Product;
use Tests\Fixtures\PostMeta\SchemalessArray;
use Tests\Fixtures\PostTypeFixture;

/**
 * @return list<array<string, mixed>>
 */
function discoveredMeta(string $fixture): array
{
    $discovery = new PostMetaDiscovery();

    discoverFixture($discovery, $fixture);

    return array_values(iterator_to_array($discovery->getItems()));
}

describe('PostMetaDiscovery', function () {
    it('discovers one item per attribute', function () {
        expect(discoveredMeta(Product::class))->toHaveCount(3);
    });

    it('keeps the attribute instance on the item', function () {
        $items = discoveredMeta(Product::class);

        expect($items[0]['attribute'])->toBeInstanceOf(AsPostMeta::class);
        expect($items[0]['attribute']->key)->toBe('price');
        expect($items[0]['attribute']->type)->toBe('number');
        expect($items[0]['className'])->toBe(Product::class);
    });

    it('ignores a class with no meta declarations', function () {
        expect(discoveredMeta(PostTypeFixture::class))->toHaveCount(0);
    });

    it('infers the post type from the model that declares the key', function () {
        // Without a subtype, register_meta() registers the key for every post
        // type. A model declaring a key on itself never means that.
        foreach (discoveredMeta(Product::class) as $item) {
            expect($item['objectSubtype'])->toBe('product');
        }
    });

    it('infers the taxonomy for a term key', function () {
        expect(discoveredMeta(Genre::class)[0]['objectSubtype'])->toBe('genre');
    });

    it('falls back to the Timber model type', function () {
        // A model for a post type registered elsewhere still knows which one.
        expect(discoveredMeta(Article::class)[0]['objectSubtype'])->toBe('post');
    });

    it('takes an explicit subtype over the inferred one', function () {
        expect(discoveredMeta(Book::class)[0]['objectSubtype'])->toBe('page');
    });

    it('leaves a user key with no subtype', function () {
        // Users and comments have no subtypes in WordPress.
        expect(discoveredMeta(Book::class)[1]['objectSubtype'])->toBe('');
    });

    it('accepts an array with a schema, and one kept out of REST', function () {
        expect(discoveredMeta(Gallery::class))->toHaveCount(2);
    });

    it('ignores an abstract class carrying declarations its children inherit', function () {
        expect(discoveredMeta(AbstractBase::class))->toHaveCount(0);
    });

    it('rejects a type register_meta does not accept', function () {
        expect(fn() => discoveredMeta(InvalidType::class))
            ->toThrow(InvalidArgumentException::class, "declares type 'float'");
    });

    it('rejects an object type register_meta does not accept', function () {
        expect(fn() => discoveredMeta(InvalidObjectType::class))
            ->toThrow(InvalidArgumentException::class, "declares objectType 'widget'");
    });

    it('rejects an array shown in REST with no schema', function () {
        // WordPress refuses to build a REST schema for an array whose items it
        // cannot describe, and says so only under WP_DEBUG.
        expect(fn() => discoveredMeta(SchemalessArray::class))
            ->toThrow(InvalidArgumentException::class, 'needs a schema');
    });

    it('rejects a sanitizer that does not exist', function () {
        expect(fn() => discoveredMeta(MissingSanitizer::class))
            ->toThrow(InvalidArgumentException::class, 'does not exist');
    });

    it('rejects a sanitizer that is not static', function () {
        // Timber\Post declares a protected constructor, so there is nothing to
        // call an instance method on when apply() runs.
        expect(fn() => discoveredMeta(InstanceSanitizer::class))
            ->toThrow(InvalidArgumentException::class, 'must be public static');
    });
});
