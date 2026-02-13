# Render API

Føhn provides an optional REST endpoint for rendering Twig templates via AJAX. This enables cacheable partial loading for features like "load more" buttons, infinite scroll, or dynamic content updates.

## Enabling the Render API

The Render API is opt-in. Enable it by adding `RenderApiHook` to your hooks configuration:

```php
use Studiometa\Foehn\Hooks\RenderApiHook;

Kernel::boot(__DIR__, [
    'hooks' => [
        RenderApiHook::class,
    ],
]);
```

## Configuration

Configure the allowed templates by creating a `render-api.config.php` file in your `app/` directory:

```php
// app/render-api.config.php
use Studiometa\Foehn\Config\RenderApiConfig;

return new RenderApiConfig(
    templates: ['partials/*', 'components/*'],
    cacheMaxAge: 300, // 5 minutes
    debug: false,
);
```

This file is automatically discovered by Tempest's config loader.

| Option        | Type       | Default | Description                                     |
| ------------- | ---------- | ------- | ----------------------------------------------- |
| `templates`   | `string[]` | `[]`    | Allowed template patterns (supports `*`)        |
| `cacheMaxAge` | `int`      | `0`     | Cache-Control max-age in seconds (0 to disable) |
| `debug`       | `bool`     | `false` | Include exception details in error messages     |

::: warning Security
Only templates matching the configured patterns can be rendered. Always restrict to specific directories to prevent unauthorized template access.
:::

## Endpoint

```
GET /wp-json/foehn/v1/render
```

### Parameters

| Parameter   | Type     | Required | Description                         |
| ----------- | -------- | -------- | ----------------------------------- |
| `template`  | `string` | \*       | Single template path                |
| `templates` | `object` | \*       | Multiple templates (key → path)     |
| `*`         | `scalar` | No       | Any scalar values passed to context |

\* Either `template` or `templates` is required.

### Single Template Response

```
GET /wp-json/foehn/v1/render?template=partials/card&title=Hello
```

```json
{
  "html": "<div class=\"card\">Hello</div>"
}
```

### Multiple Templates Response

Render multiple templates in a single request, reducing round-trips (inspired by [Shopify's Section Rendering API](https://shopify.dev/docs/api/ajax/section-rendering)):

```
GET /wp-json/foehn/v1/render?templates[hero]=blocks/hero&templates[card]=partials/card&title=Hello
```

```json
{
  "hero": "<section class=\"hero\">Hello</section>",
  "card": "<article class=\"card\">Hello</article>"
}
```

## Context

Templates receive only scalar values from query parameters. For complex data like posts or terms, use a [Context Provider](/guide/context-providers).

### Basic Context

All scalar query parameters (except `template` and `templates`) are passed to the template:

```twig
{# GET /wp-json/foehn/v1/render?template=partials/card&title=Hello&count=5 #}
<div class="card">
  <h2>{{ title }}</h2>
  <span>{{ count }} items</span>
</div>
```

### Resolving Posts and Terms

Use a Context Provider to resolve IDs to Timber objects:

```php
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Timber;

#[AsContextProvider('partials/*')]
final class PostContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        $postId = $context->get('post_id');
        if ($postId !== null) {
            $post = Timber::get_post((int) $postId);

            if ($post && $post->post_status === 'publish') {
                $context = $context->with('post', $post);
            }
        }

        $termId = $context->get('term_id');
        if ($termId !== null) {
            $taxonomy = $context->get('taxonomy', 'category');
            $context = $context->with('term', Timber::get_term_by('id', (int) $termId, $taxonomy));
        }

        return $context;
    }
}
```

Now you can use `post_id` and `term_id` parameters:

```
GET /wp-json/foehn/v1/render?template=partials/card&post_id=123
```

```twig
<article class="card">
  <h2>{{ post.title }}</h2>
  <p>{{ post.preview }}</p>
  <a href="{{ post.link }}">Read more</a>
</article>
```

## Usage Examples

### Load More Posts

**JavaScript**:

```js
async function loadMorePosts(page) {
  const postIds = await fetchPostIds(page);

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

### Render Multiple Sections

Fetch multiple page sections in a single request:

```js
async function refreshPageSections(postId) {
  const params = new URLSearchParams();
  params.set("templates[header]", "partials/header");
  params.set("templates[sidebar]", "partials/sidebar");
  params.set("templates[footer]", "partials/footer");
  params.set("post_id", postId);

  const response = await fetch(`/wp-json/foehn/v1/render?${params}`);
  const sections = await response.json();

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

## Security Considerations

1. **Template allowlist**: Only explicitly allowed template patterns can be rendered
2. **Scalar values only**: Context parameters are limited to scalar types (strings, numbers, booleans)
3. **No sensitive data**: Don't pass sensitive information via query parameters
4. **Post/term access**: If using a Context Provider to resolve posts/terms, ensure you check `post_status` and permissions

## Error Responses

| Status | Code                   | Description                                     |
| ------ | ---------------------- | ----------------------------------------------- |
| 400    | `missing_template`     | No template specified                           |
| 400    | `invalid_templates`    | templates parameter is not an object of strings |
| 403    | `template_not_allowed` | Template not in allowlist                       |
| 500    | `render_error`         | Template rendering failed                       |

## See Also

- [Context Providers](/guide/context-providers) - Add dynamic data to templates
- [REST API](/guide/rest-api) - Custom REST endpoints
