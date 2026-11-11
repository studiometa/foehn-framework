<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * Sets one key itself and inherits the others. Not an #[AsTemplateController], so
 * only ContextKeysTest reads it.
 */
final class OutletController extends ShopController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        $context = $this->shop($context)->with('outlet', true);

        return null;
    }
}
