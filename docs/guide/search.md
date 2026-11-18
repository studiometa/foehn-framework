# Search

`studiometa/foehn-search` is a native search for the search page of a site: typo tolerance and relevance, with no licence and no external service. It uses [Loupe](https://github.com/loupe-php/loupe), a search engine in PHP that stores its index in SQLite files on the server. Templates, pagination and [Facets](/guide/query-filters) stay as they are.

## Installation

```bash
composer require studiometa/foehn-search
```

The package requires the exact version of `studiometa/foehn`. Upgrade the two packages together.

PHP needs these extensions:

| Extension    | Why                                                              |
| ------------ | ---------------------------------------------------------------- |
| `pdo_sqlite` | Loupe stores each index in SQLite. SQLite must be 3.35 or later. |
| `intl`       | Loupe splits text into words, also in languages without spaces.  |

The [Føhn runtime image](/guide/docker-image) has both, with SQLite 3.53.

Loupe is made for fewer than about 50,000 documents. Above that, use a search service.

## Make a post type searchable

Add a class with `#[AsSearchDocument]` that implements `SearchDocumentInterface`. It turns one published post into the fields of a search document. `Document::fromPost()` gives the default fields: the title, the excerpt and the content as plain text, the names of the terms of the post, and the date.

```php
<?php

namespace App\Search;

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Document;
use WP_Post;

#[AsSearchDocument(postType: 'product')]
final class ProductDocument implements SearchDocumentInterface
{
    public function toDocument(WP_Post $post): ?array
    {
        return [
            ...Document::fromPost($post),
            'reference' => (string) get_post_meta($post->ID, 'reference', true),
        ];
    }
}
```

Return `null` to keep a post out of the index. A post that is not published or has a password is never in the index. The index adds `id` and `post_type` itself.

Then build the index:

```bash
wp foehn-search reindex
```

Run it after the first install, and after a change of a document class or of `searchableAttributes`. The command is `wp foehn-search reindex`, because the commands of a package go under the name of the package (see [CLI Commands](/guide/cli-commands)).

## Index updates

The index follows the content. A post is indexed again when it is saved, trashed, restored, published, deleted, or when its terms change, and the posts of a term are indexed again when the term is renamed or deleted.

The ids of one request are indexed once, at `shutdown`, through an `#[AsJob]` job when [Action Scheduler](/guide/cron) is installed. Without Action Scheduler, the request indexes them itself.

## The search page

With `?s=`, the index does the text match only. It gives every matching id, the most relevant first, and the main query gets them as `post__in`, ordered by `post__in`. WordPress then does what it does on any archive:

- the taxonomy filters of the URL, such as `?s=bread&category_name=news`, and the [query filters](/guide/query-filters);
- the pages;
- the language, with Polylang or WPML;
- the counts of [`Facets`](/guide/query-filters#facets), each facet counted with its own filter lifted.

The index gives every result: there is no cap below `maxResults` (50,000, the size Loupe is made for). `Facets` stops counting above 2,000 results, as on any archive.

An empty search (`?s=`) gives no result, not every post.

Without a post type in the URL, the search covers the searchable post types. With `?post_type=product`, it covers that type only.

The search page stays outside the [page cache](/guide/page-cache): a search is a bypass.

## Results grouped by post type

`SearchGroups` gives the results of each post type: the number of results after the filters, the most relevant posts, and the URL of the search limited to that post type, for a "see all" link. The groups are in the order of their most relevant result.

```php
use Studiometa\Foehn\Search\Query\SearchGroups;

use function Tempest\Container\get;

$context['groups'] = get(SearchGroups::class)->for(perGroup: 3);
```

```twig
{% for group in groups %}
  <section>
    <h2>{{ group.label }} ({{ group.count }})</h2>
    {% for post in group.posts %}
      <a href="{{ fn('get_permalink', post) }}">{{ post.post_title }}</a>
    {% endfor %}
    <a href="{{ group.url }}">See all</a>
  </section>
{% endfor %}
```

## Suggestions

`GET /wp-json/foehn-search/v1/suggestions?q=brea` gives the best matches while a visitor types:

```json
[{ "id": 12, "title": "Bread", "type": "post", "url": "https://example.com/bread/" }]
```

The `lang` parameter selects the language. Without it, or with a language the site does not have, the current language is used. The response has `Cache-Control: public, max-age=300`.

## Languages

Each language has its own index, set to that language only. A short query has too few words for Loupe to find its language, and a query stemmed in the wrong language loses results. The languages come from the [`Multilingual` contract](/guide/polylang#the-multilingual-contract). A post goes into the index of its language. The search page and the suggestions read the index of the current language.

On a site with one language, the language is the one of the site locale.

## Configuration

```php
// app/search.config.php
use Studiometa\Foehn\Search\Config\SearchConfig;

return new SearchConfig(
    searchableAttributes: ['title', 'reference', 'terms', 'excerpt', 'content'],
);
```

| Option                 | Default                                    | Description                                                            |
| ---------------------- | ------------------------------------------ | ---------------------------------------------------------------------- |
| `directory`            | `storage/search` in the project root       | Where the indexes are stored, one directory for each language.         |
| `searchableAttributes` | `['title', 'terms', 'excerpt', 'content']` | The fields the search reads. A match in an earlier field ranks higher. |
| `maxResults`           | `50000`                                    | The most results one search gives.                                     |
| `suggestions`          | `5`                                        | The number of suggestions.                                             |
| `suggestionsMaxAge`    | `300`                                      | The `max-age` of the suggestions, in seconds.                          |

The default directory is beside the web root, so the web server does not serve it. Add `/storage/` to `.gitignore`. The directory must be writable by PHP and must survive a deploy: on a container host, put it on a volume, and run `wp foehn-search reindex` when the volume is new.

## Limits

- **One machine.** Each machine has its own index files. On a host with several machines, such as several Fly machines, a save updates the index of one machine only. This version supports one machine.
- **Large result sets.** `post__in` with many thousands of ids makes the main query and the facet counts slower. Above about 10,000 results, counting in Loupe would be faster; this version does not do it.
