<?php

declare(strict_types=1);

/**
 * A mu-plugin that the JSON-LD tests install: on requests with `?smoke-json-ld-block`, it
 * adds a node as a block does when it renders.
 */
add_action('wp', static function (): void {
    if (!isset($_GET['smoke-json-ld-block'])) {
        return;
    }

    \Studiometa\Foehn\JsonLd\JsonLd::add([
        '@type' => 'FAQPage',
        '@id' => home_url('/#smoke-faq'),
        'mainEntity' => [[
            '@type' => 'Question',
            'name' => 'Does </script> close the script?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No.'],
        ]],
    ]);
});
