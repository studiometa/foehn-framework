<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\CanonicalRedirect;

/**
 * Keeping a comma a comma.
 *
 * `redirect_canonical()` rebuilds the query string of a paginated URL with its values
 * encoded, so a request for `?foehn_sections=count,index` is answered with a 301 to
 * `?foehn_sections=count%2Cindex`. The comma is in the cache's value charset, so the
 * recorder stores the response under a filename with a literal comma while nginx — which
 * keys from the raw query string — then looks for one with `%2C` in it. Nothing errors.
 * The cache simply stops being read.
 *
 * The two behaviours to pin: a redirect that is only about that encoding is cancelled,
 * and a redirect that also does something real keeps its literal comma.
 */

it('cancels a redirect whose only change is encoding a comma', function () {
    $filter = new CanonicalRedirect();

    expect($filter->filter(
        'https://example.test/projects/page/2/?foehn_sections=count%2Cindex',
        'https://example.test/projects/page/2/?foehn_sections=count,index',
    ))->toBeFalse();
});

it('keeps a redirect that does something else, with the comma spelled as a comma', function () {
    // A missing trailing slash is a real canonical redirect and has to survive. Only the
    // encoding of the comma is this class's business.
    $filter = new CanonicalRedirect();

    expect($filter->filter(
        'https://example.test/projects/page/2/?foehn_sections=count%2Cindex',
        'https://example.test/projects/page/2?foehn_sections=count,index',
    ))
        ->toBe('https://example.test/projects/page/2/?foehn_sections=count,index');
});

it('leaves alone a redirect with no comma in it', function () {
    $filter = new CanonicalRedirect();

    expect($filter->filter(
        'https://example.test/projects/?orderby=title',
        'https://example.test/projects?orderby=title',
    ))
        ->toBe('https://example.test/projects/?orderby=title');
});

it('normalises the lowercase spelling a client may send', function () {
    $filter = new CanonicalRedirect();

    expect($filter->filter(
        'https://example.test/projects/?tag=a%2cb',
        'https://example.test/projects/?tag=a,b',
    ))->toBeFalse();
});

it('leaves a percent-encoded comma in a path alone', function () {
    // Only the query string is normalised. A `%2C` in a path could be meant, and no part
    // of this problem is there — so a redirect involving one is still a redirect.
    $filter = new CanonicalRedirect();

    expect($filter->filter('https://example.test/a%2Cb/', 'https://example.test/a,b/'))
        ->toBe('https://example.test/a%2Cb/');
});

it('keeps a fragment where it was', function () {
    $filter = new CanonicalRedirect();

    expect($filter->filter(
        'https://example.test/projects/page/2/?foehn_sections=count%2Cindex#list',
        'https://example.test/projects/page/2?foehn_sections=count,index#list',
    ))
        ->toBe('https://example.test/projects/page/2/?foehn_sections=count,index#list');
});

it('passes through a request core already decided not to redirect', function (mixed $redirect) {
    // `false` is core's own "stay here", and another filter may have said it first.
    $filter = new CanonicalRedirect();

    expect($filter->filter($redirect, 'https://example.test/projects/'))->toBeFalse();
})->with([
    [false],
    [''],
    [null],
]);
