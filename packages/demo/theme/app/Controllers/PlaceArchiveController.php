<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Places\Search\PlaceSearch;
use Studiometa\Foehn\Places\View\PlacesView;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * The venue locator, at /venues/: one search result for the page and its four sections.
 *
 * The theme owns the layout. The package owns the fragments, which receive `result`
 * and `places` and nothing else. A section request runs this controller too, so the
 * filters, the summary, the results and the pagination always describe the same search.
 *
 * Priority 5, so it wins over `ArchiveController`'s `archive-*`.
 */
#[AsTemplateController('archive-place', priority: 5)]
final readonly class PlaceArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
        private PlacesView $places,
    ) {}

    public function handle(TemplateContext $context): string
    {
        return $this->view->render('pages/archive-place', $context->with('result', PlaceSearch::current())->with(
            'places',
            $this->places,
        ));
    }
}
