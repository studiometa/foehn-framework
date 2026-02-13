# PostQueryBuilder

Fluent query builder for WordPress posts. Accumulates `WP_Query` parameters and delegates to `Timber::get_posts()`.

## Constructor

```php
new PostQueryBuilder(string $postType)
```

Typically obtained via the `QueriesPostType` trait:

```php
$query = Product::query(); // Returns PostQueryBuilder for 'product' type
```

## Pagination

```php
$query->limit(int $limit): self          // posts_per_page (-1 for all)
$query->offset(int $offset): self        // Skip N posts
$query->page(int $page): self            // Set page number (no-op if ≤ 0)
```

## Ordering

```php
$query->orderBy(string $field, string $order = 'DESC'): self
$query->orderByMeta(string $key, string $order = 'DESC', bool $numeric = false): self
```

## Filtering

### Status

```php
$query->status(string|array $status): self
```

### IDs

```php
$query->include(int ...$ids): self       // No-op if empty
$query->exclude(int ...$ids): self       // No-op if empty
```

### Taxonomy

```php
$query->whereTax(
    string $taxonomy,
    string|int|array|null $terms,        // No-op if null/empty
    string $field = 'slug',              // 'slug', 'term_id', 'name'
    string $operator = 'IN',             // 'IN', 'NOT IN', 'AND', 'EXISTS'
): self

$query->taxRelation(string $relation): self  // 'AND' or 'OR'
```

### Meta

```php
$query->whereMeta(
    string $key,
    mixed $value,
    string $compare = '=',              // '=', '!=', '>', 'LIKE', 'BETWEEN', etc.
    ?string $type = null,               // 'NUMERIC', 'CHAR', 'DATE', etc.
): self

$query->metaRelation(string $relation): self  // 'AND' or 'OR'
```

### Search

```php
$query->search(string $terms): self      // No-op if empty
```

### Author

```php
$query->byAuthor(int $authorId): self
```

### Date

```php
$query->dateQuery(array $dateQuery): self
```

### Parent (Hierarchical Types)

```php
$query->parent(int $parentId): self
$query->parentIn(int ...$parentIds): self     // No-op if empty
$query->parentNotIn(int ...$parentIds): self  // No-op if empty
```

## Escape Hatch

```php
$query->set(string $key, mixed $value): self
$query->merge(array $args): self
```

## Execution

```php
$query->get(): array             // list<\Timber\Post>
$query->first(): mixed           // \Timber\Post|null
$query->count(): int             // Count via fields: ids
$query->exists(): bool           // Any matching post?
$query->getParameters(): array   // Raw WP_Query params (debug)
```

## Null-Safety

All filtering methods silently skip empty/null values, making it safe to pass user input directly:

```php
Product::query()
    ->whereTax('category', $request->get('category'))  // Skipped if null
    ->search($request->get('q') ?? '')                  // Skipped if ''
    ->get();
```

## Related

- [Guide: Querying Posts](/guide/querying-posts)
- [QueriesPostType](./queries-post-type)
