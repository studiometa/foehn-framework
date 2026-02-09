# Render API

Føhn provides an optional REST endpoint for rendering Twig templates via AJAX. This enables cacheable partial loading for features like "load more" buttons, infinite scroll, or dynamic content updates.

## Configuration

Enable the Render API in your theme's `functions.php`:

```php
Kernel::boot(__DIR__, [
    'render_api' => [
        'enabled' => true,
        'templates' => ['partials/*', 'blocks/*', 'components/*'],
    ],
]);
```

| Option      | Type       | Default | Description                              |
| ----------- | ---------- | ------- | ---------------------------------------- |
| `enabled`   | `bool`     | `true`  | Enable/disable the endpoint              |
| `templates` | `string[]` | `[]`    | Allowed template patterns (supports `*`) |

::: warning Security
Only templates matching the configured patterns can be rendered. Always restrict to specific directories to prevent unauthorized template access.
:::

## Endpoint

```
GET /wp-json/foehn/v1/render
```

### Parameters

| Parameter   | Type      | Required | Description                                |
| ----------- | --------- | -------- | ------------------------------------------ |
| `template`  | `string`  | \*       | Single template path                       |
| `templates` | `object`  | \*       | Multiple templates (key → path)            |
| `post_id`   | `integer` | No       | Post ID to resolve as `post` context       |
| `term_id`   | `integer` | No       | Term ID to resolve as `term` context       |
| `taxonomy`  | `string`  | No       | Taxonomy for term_id (default: `category`) |
| `*`         | `scalar`  | No       | Any other scalar values passed to context  |

\* Either `template` or `templates` is required.

### Single Template Response

```
GET /wp-json/foehn/v1/render?template=partials/card&post_id=123
```

```json
{
  "html": "<div class=\"card\">...</div>"
}
```

### Multiple Templates Response

Render multiple templates in a single request, reducing round-trips (inspired by [Shopify's Section Rendering API](https://shopify.dev/docs/api/ajax/section-rendering)):

```
GET /wp-json/foehn/v1/render?templates[hero]=blocks/hero&templates[card]=partials/card&post_id=123
```

```json
{
  "hero": "<section class=\"hero\">...</section>",
  "card": "<article class=\"card\">...</article>"
}
```

## Usage Examples

### Load More Posts

**Template** (`templates/partials/card.twig`):

```twig
<article class="card">
  <h2>{{ post.title }}</h2>
  <p>{{ post.preview }}</p>
  <a href="{{ post.link }}">Read more</a>
</article>
```

**JavaScript**:

```js
async function loadMorePosts(page) {
  const postIds = await fetchPostIds(page); // Your logic to get post IDs

  const cards = await Promise.all(
    postIds.map(async (id) => {
      const response = await fetch(`/wp-json/foehn/v1/render?template=partials/card&post_id=${id}`);
      const { html } = await response.json();
      return html;
    }),
  );

  document.querySelector(".posts-grid").insertAdjacentHTML("beforeend", cards.join(""));
}
```

### Render Component with Custom Data

**Template** (`templates/components/button.twig`):

```twig
<a href="{{ url }}" class="btn btn--{{ variant }}">
  {{ label }}
</a>
```

**JavaScript**:

```js
async function renderButton(label, url, variant = "primary") {
  const params = new URLSearchParams({
    template: "components/button",
    label,
    url,
    variant,
  });

  const response = await fetch(`/wp-json/foehn/v1/render?${params}`);
  const { html } = await response.json();

  return html;
}
```

### Render Term Card

**Template** (`templates/partials/category-card.twig`):

```twig
<div class="category-card">
  <h3>{{ term.name }}</h3>
  <p>{{ term.description }}</p>
  <span class="count">{{ term.count }} posts</span>
</div>
```

**JavaScript**:

```js
const response = await fetch(
  "/wp-json/foehn/v1/render?template=partials/category-card&term_id=5&taxonomy=category",
);
const { html } = await response.json();
```

### Render Multiple Sections

Fetch multiple page sections in a single request:

**JavaScript**:

```js
async function refreshPageSections(postId) {
  const params = new URLSearchParams();
  params.set("templates[header]", "partials/header");
  params.set("templates[sidebar]", "partials/sidebar");
  params.set("templates[footer]", "partials/footer");
  params.set("post_id", postId);

  const response = await fetch(`/wp-json/foehn/v1/render?${params}`);
  const sections = await response.json();

  // Update each section
  document.querySelector(".header").innerHTML = sections.header;
  document.querySelector(".sidebar").innerHTML = sections.sidebar;
  document.querySelector(".footer").innerHTML = sections.footer;
}
```

## Caching

The Render API uses GET requests, making responses cacheable by:

- **Browser cache** via Cache-Control headers
- **CDN/Edge caching** (Cloudflare, Fastly, etc.)
- **WordPress caching plugins** (WP Rocket, WP Super Cache, etc.)

::: tip WP Rocket
WP Rocket can cache REST API responses. Ensure your allowed templates don't contain user-specific data, or exclude specific patterns from caching.
:::

## Security Considerations

1. **Template allowlist**: Only explicitly allowed template patterns can be rendered
2. **Public posts only**: `post_id` only resolves published posts (not drafts, private, etc.)
3. **Scalar values only**: Context parameters are limited to scalar types (strings, numbers, booleans)
4. **No sensitive data**: Don't pass sensitive information via query parameters

## Error Responses

| Status | Code                   | Description                    |
| ------ | ---------------------- | ------------------------------ |
| 404    | `template_not_allowed` | Template not in allowlist      |
| 404    | `invalid_context`      | Referenced post/term not found |
| 404    | `render_error`         | Template rendering failed      |

## See Also

- [REST API](/guide/rest-api) - Custom REST endpoints
