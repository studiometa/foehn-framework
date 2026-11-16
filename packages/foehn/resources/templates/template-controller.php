<?php

/**
 * The template file WordPress includes for a request a template controller handles.
 *
 * `TemplateControllerDiscovery::handleTemplateInclude()` returns this path from
 * `template_include`, so WordPress fires `wp_before_include_template` and starts its
 * template enhancement output buffer before the page is rendered. The controller runs
 * here. When it returns null, the template file WordPress chose is included instead,
 * from the global scope, as WordPress would include it.
 */

declare(strict_types=1);

$foehnTemplate = Studiometa\Foehn\Discovery\TemplateControllerDiscovery::renderPending();

if ($foehnTemplate !== null) {
    include $foehnTemplate;
}
