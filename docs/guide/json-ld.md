# JSON-LD

Search engines and generative AI answers read [schema.org](https://schema.org) structured data from the JSON-LD of a page. Føhn adds the JSON-LD of a theme to the page:

- **Page nodes** come from `#[AsJsonLd]` classes. A class gives the nodes of the single pages of one or more post types.
- **Block nodes** come from `JsonLd::add()`. A block, such as a FAQ block, adds a node when it renders.

Føhn needs no library. A node is an array, a `JsonSerializable` object or an object with a `toArray()` method. The examples on this page use [`spatie/schema-org`](https://github.com/spatie/schema-org), which the starter requires. A project can remove it and write arrays.

## Where Føhn prints the nodes

| Nodes       | Rank Math prints its graph                                        | No SEO plugin, or Rank Math prints no graph              |
| ----------- | ----------------------------------------------------------------- | -------------------------------------------------------- |
| Page nodes  | In the graph of Rank Math, through the `rank_math/json_ld` filter | In one `<script type="application/ld+json">` in `<head>` |
| Block nodes | In a second `<script type="application/ld+json">` on `wp_footer`  | The same                                                 |

Føhn does not read the settings of Rank Math: on `wp_head`, it prints its own script when Rank Math did not apply the `rank_math/json_ld` filter. Rank Math prints no graph while its Schema module is off, and while its registration is not done or skipped: it then does not load its front end.

Rank Math prints its graph in `<head>`, before the blocks render. Thus the block nodes always go in the footer. The theme must call `wp_footer()`, as the starter layout does.

Each script of Føhn holds one `@graph` and has the class `foehn-json-ld`:

```html
<script type="application/ld+json" class="foehn-json-ld">
  {"@context":"https://schema.org","@graph":[{"@type":"Event","@id":"https://example.com/events/concert/#event", …}]}
</script>
```

Føhn removes the `@context` of each node, because the graph holds it once. Føhn does not validate the nodes against the fields that Google requires: no library does it. Use the [Rich Results Test](https://search.google.com/test/rich-results) or the [Schema Markup Validator](https://validator.schema.org/).

## Give each node a stable `@id`

A node refers to another node with its `@id`. Use the URL of the page followed by a fragment, such as `#event`. The same page then always gives the same `@id`.

With Rank Math, refer to its nodes by their `@id`:

| Node of Rank Math          | `@id`                      |
| -------------------------- | -------------------------- |
| `WebPage` of the page      | `<permalink>#webpage`      |
| `Organization` of the site | `<home URL>/#organization` |
| `WebSite`                  | `<home URL>/#website`      |

## Page nodes: `#[AsJsonLd]`

A class with `#[AsJsonLd]` implements `JsonLdProviderInterface`. Its `nodes()` method gets the post of the single page and returns a list of nodes:

```php
<?php

namespace App\JsonLd;

use Spatie\SchemaOrg\Schema;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use WP_Post;

#[AsJsonLd(postType: 'event')]
final class EventJsonLd implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        $url = get_permalink($post);

        return [
            Schema::event()
                ->identifier($url . '#event')
                ->name(get_the_title($post))
                ->url($url)
                ->startDate(new \DateTimeImmutable(get_post_meta($post->ID, 'start_date', true)))
                ->location(
                    Schema::place()
                        ->name(get_post_meta($post->ID, 'venue', true))
                        ->address(get_post_meta($post->ID, 'address', true)),
                )
                ->organizer(['@id' => home_url('/#organization')]),
        ];
    }
}
```

`postType` takes one post type or a list: `#[AsJsonLd(postType: ['event', 'festival'])]`. Several classes can give nodes for the same post type: Føhn adds the nodes of each class.

Føhn calls `nodes()` only on the single page of a post (`is_singular()`) of one of these post types. It resolves the class from the container, so its constructor can ask for services.

The starter ships `App\JsonLd\PostJsonLd`, which gives a `BlogPosting` node to each post. Rank Math gives posts an `Article` node of its own: with Rank Math, set the schema type of posts to "None" (Rank Math > Titles & Meta > Posts), or delete the class.

### A recipe

```php
#[AsJsonLd(postType: 'recipe')]
final class RecipeJsonLd implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        $url = get_permalink($post);

        return [
            Schema::recipe()
                ->identifier($url . '#recipe')
                ->name(get_the_title($post))
                ->image(get_the_post_thumbnail_url($post, 'large'))
                ->recipeIngredient(get_post_meta($post->ID, 'ingredients', false))
                ->recipeInstructions(array_map(
                    static fn(string $step) => Schema::howToStep()->text($step),
                    get_post_meta($post->ID, 'steps', false),
                ))
                ->mainEntityOfPage(['@id' => $url . '#webpage']),
        ];
    }
}
```

### A local business, as an array

A node does not need a library. This class gives the `LocalBusiness` node of each `shop` post, as a plain array:

```php
#[AsJsonLd(postType: 'shop')]
final class ShopJsonLd implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        $url = get_permalink($post);

        return [
            [
                '@type' => 'LocalBusiness',
                '@id' => $url . '#shop',
                'name' => get_the_title($post),
                'url' => $url,
                'telephone' => get_post_meta($post->ID, 'phone', true),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => get_post_meta($post->ID, 'street', true),
                    'postalCode' => get_post_meta($post->ID, 'postal_code', true),
                    'addressLocality' => get_post_meta($post->ID, 'city', true),
                    'addressCountry' => 'FR',
                ],
                'openingHours' => ['Mo-Fr 09:00-19:00', 'Sa 10:00-18:00'],
                'parentOrganization' => ['@id' => home_url('/#organization')],
            ],
        ];
    }
}
```

## Block nodes: `JsonLd::add()`

A block adds its node when it renders. Føhn does not ship a FAQ block: blocks stay in the theme. This FAQ block adds a `FAQPage` node:

```php
<?php

namespace App\Blocks\Faq;

use Spatie\SchemaOrg\Schema;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\JsonLd\JsonLd;
use WP_Block;

#[AsBlock(name: 'theme/faq', title: 'FAQ')]
final readonly class FaqBlock implements BlockInterface
{
    // attributes() and render() as in any block.

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $items = $attributes['items'] ?? [];

        JsonLd::add(
            Schema::fAQPage()
                ->identifier(get_permalink() . '#faq')
                ->mainEntity(array_map(
                    static fn(array $item) => Schema::question()
                        ->name($item['question'])
                        ->acceptedAnswer(Schema::answer()->text($item['answer'])),
                    $items,
                )),
        );

        return ['items' => $items];
    }
}
```

`JsonLd::add()` accepts the same nodes as `nodes()`. It throws an `InvalidArgumentException` for an object that it cannot turn into an array. Føhn prints the nodes once, on `wp_footer`, then empties the list.

## The page cache

The [page cache](/guide/page-cache) stores the HTML of the page with its scripts. A change to a node shows when the stored page is purged, as for any other change of the HTML.

## Out of scope

Føhn does not add nodes to the schema of Yoast SEO. With Yoast, Føhn prints its own script in `<head>`: a page can hold more than one JSON-LD script.

## Related

- [`#[AsJsonLd]`](/api/as-json-ld)
- [`JsonLdProviderInterface`](/api/json-ld-provider-interface)
- [Rank Math](/guide/rank-math)
