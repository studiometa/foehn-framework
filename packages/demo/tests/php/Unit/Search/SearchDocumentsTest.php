<?php

declare(strict_types=1);

use Demo\Search\PageDocument;
use Demo\Search\PostDocument;
use Demo\Search\ProjectDocument;
use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Config\SearchConfig;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;

describe('Search documents', function () {
    it('makes the projects, the journal posts and the pages searchable', function (string $class, string $postType) {
        $attributes = new ReflectionClass($class)->getAttributes(AsSearchDocument::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->postType)->toBe($postType);
        expect(is_subclass_of($class, SearchDocumentInterface::class))->toBeTrue();
    })->with([
        'projects' => [ProjectDocument::class, 'project'],
        'journal posts' => [PostDocument::class, 'post'],
        'pages' => [PageDocument::class, 'page'],
    ]);

    it('searches the two fields a project document adds', function () {
        $config = require dirname(__DIR__, 4) . '/theme/app/search.config.php';

        expect($config)->toBeInstanceOf(SearchConfig::class);
        expect($config->searchableAttributes)->toContain('client', 'location');
    });
});
