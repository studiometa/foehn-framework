### Added

- `#[AsJsonLd(postType: '…')]` adds schema.org JSON-LD to the single pages of a post type: the class implements `JsonLdProviderInterface` and its `nodes()` method returns arrays, `JsonSerializable` objects or objects with `toArray()`. When Rank Math prints its graph, the nodes go in it. Else Føhn prints one `<script type="application/ld+json">` with an `@graph` in `<head>`. See the JSON-LD guide
- `JsonLd::add($node)` lets a block, such as a FAQ block, add a node when it renders. Føhn prints these nodes in a second script on `wp_footer`, with or without Rank Math. The theme must call `wp_footer()`
- The starter requires `spatie/schema-org` and ships `App\JsonLd\PostJsonLd`, which gives each post a `BlogPosting` node. A project that uses Rank Math sets the schema type of posts to "None" in Rank Math, or deletes the class. The framework only suggests `spatie/schema-org`
