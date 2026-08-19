# Block Bindings

Block bindings connect a block attribute — a paragraph's text, an image's `alt`, a button's `url` — to a value computed at render time, without a custom block.

## Read this first: the common case needs no code

WordPress ships `core/post-meta`. A key declared with [`#[AsPostMeta]`](/api/as-post-meta) that is `single` and shown in REST is bindable through it **with no source of your own**:

```php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
#[AsPostMeta(key: 'price', type: 'number')]
final class Product extends Post {}
```

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"price"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

That is the whole feature for a value that is _stored_. `#[AsBlockBinding]` is for a value that is **computed**: a formatted price, a reading time, a figure from an external service, something assembled from several keys.

Core registers four sources of its own — `core/post-meta`, `core/post-data`, `core/term-data` and `core/pattern-overrides` — before you write anything.

## A source

```php
<?php
// app/Bindings/ReadingTime.php

namespace App\Bindings;

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use WP_Block;

#[AsBlockBinding(
    name: 'theme/reading-time',
    label: 'Reading time',
    usesContext: ['postId'],
)]
final readonly class ReadingTime implements BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        $postId = $block->context['postId'] ?? null;

        if ($postId === null) {
            return null;
        }

        $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', (int) $postId)));

        return sprintf('%d minutes read', max(1, (int) ceil($words / 200)));
    }
}
```

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"theme/reading-time"}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

| Argument      | Default    | Notes                                                                    |
| ------------- | ---------- | ------------------------------------------------------------------------ |
| `name`        | _required_ | `namespace/name`. WordPress requires the slash; Føhn refuses one without |
| `label`       | _required_ | Shown in the editor's binding UI                                         |
| `usesContext` | `[]`       | Block context keys the value needs                                       |

## Three things about `value()`

- **`usesContext` is not optional in practice.** WordPress passes nothing a source did not ask for, so a binding that needs the post and does not declare `postId` gets an empty context and nothing to work with.
- **`$attribute` names what is being bound.** One source bound to both an image's `url` and its `alt` is called twice, once for each, and answers differently.
- **Returning `null` leaves the attribute alone**, showing whatever the block author wrote. That is the right answer for "not applicable here", and better than an empty string.

The class is resolved from the container, so its constructor is autowired — and only when a bound block actually renders. A source nothing binds to costs nothing.

## Which attributes accept a binding

This is version-dependent, and no reference page enumerates it. As of **WordPress 7.0**:

| Block                     | Bindable attributes                    |
| ------------------------- | -------------------------------------- |
| `core/paragraph`          | `content`                              |
| `core/heading`            | `content`                              |
| `core/image`              | `id`, `url`, `title`, `alt`, `caption` |
| `core/button`             | `url`, `text`, `linkTarget`, `rel`     |
| `core/post-date`          | `datetime`                             |
| `core/navigation-link`    | `url`                                  |
| `core/navigation-submenu` | `url`                                  |

Check it against the version you are targeting rather than trusting a list written today — `get_block_bindings_supported_attributes()` in `wp-includes/block-bindings.php` is the list itself.

Since 6.9 the `block_bindings_supported_attributes` filter extends it, which is how a block of your own opts in:

```php
#[AsFilter('block_bindings_supported_attributes', acceptedArgs: 2)]
public function allowBindings(array $attributes, string $blockType): array
{
    return $blockType === 'theme/callout' ? [...$attributes, 'title'] : $attributes;
}
```

## Related

- [#[AsBlockBinding]](/api/as-block-binding)
- [#[AsPostMeta]](/api/as-post-meta) — bindable through `core/post-meta` with no source of your own
- [Native Blocks](/guide/native-blocks)
