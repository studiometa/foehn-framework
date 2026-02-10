<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTemplateController;

/**
 * Invalid: has #[AsTemplateController] but does NOT implement TemplateControllerInterface.
 */
#[AsTemplateController('single')]
final class InvalidTemplateControllerFixture {}
