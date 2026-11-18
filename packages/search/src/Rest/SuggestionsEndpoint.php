<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Rest;

use Studiometa\Foehn\Attributes\AsRestRoute;
use Studiometa\Foehn\Search\Config\SearchConfig;
use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\SearchIndex;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /wp-json/foehn-search/v1/suggestions?q=…&lang=…`: the best matches of what a
 * visitor types, in the language of the page.
 */
final readonly class SuggestionsEndpoint
{
    public function __construct(
        private SearchIndex $index,
        private Languages $languages,
        private SearchConfig $config,
    ) {}

    #[AsRestRoute(namespace: 'foehn-search/v1', route: '/suggestions', permission: 'public', args: [
        'q' => ['type' => 'string', 'required' => true],
        'lang' => ['type' => 'string'],
    ])]
    public function suggestions(WP_REST_Request $request): WP_REST_Response
    {
        $language = (string) $request->get_param('lang');

        if (!$this->languages->has($language)) {
            $language = $this->languages->current();
        }

        $suggestions = array_map(
            static fn(array $hit): array => [
                'id' => $hit['id'],
                'title' => $hit['title'],
                'type' => $hit['post_type'],
                'url' => (string) get_permalink($hit['id']),
            ],
            $this->index->suggest($language, (string) $request->get_param('q'), $this->config->suggestions),
        );

        $response = new WP_REST_Response($suggestions);
        // Short: a suggestion follows the index, which changes with every save.
        $response->header('Cache-Control', 'public, max-age=' . $this->config->suggestionsMaxAge);

        return $response;
    }
}
