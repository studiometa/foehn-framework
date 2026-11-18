<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use JsonSerializable;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use WP_Post;

#[AsJsonLd(postType: ['event', 'place'])]
final class JsonLdFixture implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                '@id' => 'http://example.com/' . $post->post_name . '/#event',
                'name' => 'Concert </script>',
            ],
            new class implements JsonSerializable {
                public function jsonSerialize(): array
                {
                    return ['@type' => 'Place', '@id' => 'http://example.com/#place'];
                }
            },
        ];
    }
}
