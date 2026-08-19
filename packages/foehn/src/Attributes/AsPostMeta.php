<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a meta key with WordPress.
 *
 * `register_meta()` is what puts a custom field in the REST API, and therefore in
 * the block editor and in block bindings. The attribute goes on the model that
 * owns the field, repeatably, because that model already declares its post type
 * and already holds the accessors.
 *
 * Usage:
 * ```php
 * #[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
 * #[AsPostMeta(key: 'price', type: 'number')]
 * #[AsPostMeta(key: 'sku', showInRest: false)]
 * #[AsPostMeta(key: 'gallery', type: 'integer', single: false)]
 * final class Product extends Post
 * {
 *     public function price(): ?float
 *     {
 *         return $this->meta('price');
 *     }
 * }
 * ```
 *
 * It does not conflict with ACF, which stores its values in ordinary post meta:
 * declaring a key ACF also manages leaves ACF the editing UI and gives the key a
 * REST schema.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsPostMeta
{
    /** The types `register_meta()` accepts. */
    public const array TYPES = ['string', 'boolean', 'integer', 'number', 'array', 'object'];

    /** The object types meta can be registered against. */
    public const array OBJECT_TYPES = ['post', 'term', 'user', 'comment'];

    /**
     * @param string $key The meta key
     * @param string $type One of self::TYPES
     * @param bool $single Whether one value is stored rather than a list
     * @param bool $showInRest Whether to expose the key in the REST API. On by
     *   default: without REST the field is invisible to the editor and to bindings
     * @param string $description Surfaces in the REST schema
     * @param scalar|array<array-key, mixed>|null $default Must survive var_export()
     * @param string $objectType One of self::OBJECT_TYPES
     * @param string|null $objectSubtype The post type or taxonomy the key belongs
     *   to. Inferred from #[AsPostType], #[AsTaxonomy] or #[AsTimberModel] on the
     *   same class; an empty string registers the key for every subtype
     * @param string $capability The capability required to write the key, as
     *   `auth_callback`
     * @param string|null $sanitize The name of a public static method on the
     *   declaring class, not a closure — an item reaches the discovery cache
     *   through var_export()
     * @param array<string, mixed> $schema The REST schema, required for the array
     *   and object types: WordPress cannot describe their contents on its own
     */
    public function __construct(
        public string $key,
        public string $type = 'string',
        public bool $single = true,
        public bool $showInRest = true,
        public string $description = '',
        public string|int|float|bool|array|null $default = null,
        public string $objectType = 'post',
        public ?string $objectSubtype = null,
        public string $capability = 'edit_posts',
        public ?string $sanitize = null,
        public array $schema = [],
    ) {}
}
