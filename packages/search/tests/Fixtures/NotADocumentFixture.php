<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Tests\Fixtures;

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;

/**
 * Has the attribute, not the interface: not a search document.
 */
#[AsSearchDocument(postType: 'page')]
final class NotADocumentFixture {}
