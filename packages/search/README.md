# studiometa/foehn-search

Native search for [Føhn](https://github.com/studiometa/foehn-framework), with typo tolerance and relevance, from [Loupe](https://github.com/loupe-php/loupe): SQLite files on the server, no licence and no external service.

See the [documentation](https://studiometa.github.io/foehn-framework/guide/search).

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## Installation

```bash
composer require studiometa/foehn-search
```

`studiometa/foehn-search` requires the exact same version of `studiometa/foehn`. Upgrade the two packages together, for example `composer require studiometa/foehn:<version> studiometa/foehn-search:<version>`.

PHP needs the `pdo_sqlite` extension, with SQLite 3.35 or later, and the `intl` extension. The Føhn runtime image has both.

## Why it is a package

Loupe brings `doctrine/dbal` and other dependencies that a site without search does not need.

## What it provides

- `#[AsSearchDocument(postType: '…')]`: a class that turns a post into a search document.
- The index updates on save, trash, delete and term changes, through an `#[AsJob]` job.
- The search page answered by the index, with taxonomy filters, pages and `Facets` unchanged.
- `SearchGroups`: the results grouped by post type.
- `GET /wp-json/foehn-search/v1/suggestions?q=`: suggestions while a visitor types.
- `wp foehn-search reindex`: rebuild every index.
- One index for each language, from the `Multilingual` contract.
