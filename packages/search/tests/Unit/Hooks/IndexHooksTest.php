<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\JobDispatcher;
use Studiometa\Foehn\Search\Hooks\IndexHooks;
use Studiometa\Foehn\Search\Indexer;
use Studiometa\Foehn\Search\Jobs\IndexPosts;
use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\SearchIndex;
use Studiometa\Foehn\Search\Tests\Fixtures\PostDocumentFixture;

final class RecordingJobDispatcher implements JobDispatcher
{
    /** @var list<object> */
    public array $jobs = [];

    public function __construct(
        private readonly bool $available = true,
    ) {}

    public function dispatch(object $job, ?int $delay = null): void
    {
        $this->jobs[] = $job;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}

beforeEach(function () {
    wp_stub_reset();
    $this->documents = new SearchDocuments();
    $this->documents->register('post', PostDocumentFixture::class);
    $this->dispatcher = new RecordingJobDispatcher();
    $this->hooks = new IndexHooks($this->documents, $this->dispatcher);
});

it('queues a saved post of a searchable post type', function () {
    $this->hooks->onSavePost(1, searchPost(1));
    $this->hooks->onSavePost(2, searchPost(2, ['post_type' => 'page']));
    $this->hooks->onSavePost(3, searchPost(3, ['post_type' => 'revision']));

    expect($this->hooks->pending())->toBe([1]);
});

it('queues a deleted post and a post whose terms changed', function () {
    searchPost(5);

    $this->hooks->onDeletedPost(4, searchPost(4));
    $this->hooks->onSetObjectTerms(5);

    expect($this->hooks->pending())->toBe([4, 5]);
});

it('queues the posts of an edited or deleted term', function () {
    $GLOBALS['wp_stub_objects_in_term'][7] = ['10', '11'];

    $this->hooks->onEditedTerm(7, 7, 'category');
    $this->hooks->onDeleteTerm(8, 8, 'category', null, [11, 12]);

    expect($this->hooks->pending())->toBe([10, 11, 12]);
});

it('queues nothing when no class has #[AsSearchDocument]', function () {
    $hooks = new IndexHooks(new SearchDocuments(), $this->dispatcher);
    $GLOBALS['wp_stub_objects_in_term'][7] = [10];

    $hooks->onEditedTerm(7, 7, 'category');
    $hooks->flush();

    expect($hooks->pending())->toBe([])->and($this->dispatcher->jobs)->toBe([]);
});

it('dispatches one job for every post the request changed, at shutdown', function () {
    $post = searchPost(1);
    $this->hooks->onSetObjectTerms(1);
    $this->hooks->onSavePost(1, $post);

    $this->hooks->flush();
    $this->hooks->flush();

    expect($this->dispatcher->jobs)->toHaveCount(1);
    expect($this->dispatcher->jobs[0])->toBeInstanceOf(IndexPosts::class);
    expect($this->dispatcher->jobs[0]->ids)->toBe([1]);
});

it('indexes in the request when Action Scheduler is not there', function () {
    requiresLoupe();

    $container = bootTestContainer();
    $index = testSearchIndex($directory);
    $container->singleton(SearchIndex::class, $index);
    $container->singleton(Indexer::class, new Indexer($this->documents, $index, new Languages()));

    $hooks = new IndexHooks($this->documents, new RecordingJobDispatcher(available: false));
    $hooks->onSavePost(1, searchPost(1, ['post_title' => 'Sourdough']));
    $hooks->flush();

    expect($index->ids('en', 'sourdough'))->toBe([1]);

    tearDownTestContainer();
    removeDirectory($directory);
});
