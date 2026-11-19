<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'event', singular: 'Event', plural: 'Events')]
#[AsPostMeta(key: 'subtitle', control: 'text', label: 'Subtitle', description: 'Under the title')]
#[AsPostMeta(key: 'featured', type: 'boolean', control: 'checkbox', label: 'Featured')]
#[AsPostMeta(key: 'seats', type: 'integer', control: 'number', label: 'Seats')]
#[AsPostMeta(key: 'room', control: 'select', label: 'Room', options: ['main' => 'Main hall', 'small' => 'Small hall'])]
#[AsPostMeta(key: 'code', control: 'text', sanitize: 'sanitizeCode')]
#[AsPostMeta(key: 'budget', type: 'number', control: 'number', capability: 'manage_options')]
#[AsPostMeta(key: 'internal')]
final class Event extends Post
{
    public static function sanitizeCode(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
