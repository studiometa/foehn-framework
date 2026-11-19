<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Config\SearchConfig;

/**
 * The search of the site, with studiometa/foehn-search. The documents are the classes
 * of `app/Search/`.
 *
 * The default fields, with the two fields `ProjectDocument` adds after the title: a
 * match in an earlier field ranks higher. A journal post or a page has no `client`
 * or `location`, and the search reads its other fields.
 *
 * The indexes are in `storage/search/`, beside the web root. Run
 * `wp foehn-search reindex` after a change to this file or to a document class.
 */
return new SearchConfig(searchableAttributes: ['title', 'client', 'location', 'terms', 'excerpt', 'content']);
