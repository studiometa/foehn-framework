<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

/**
 * DTO for link/button fields.
 *
 * Matches the output of ACF link fields (return_format: array)
 * as used by ButtonLinkBuilder.
 */
final readonly class LinkData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $url,
        public string $title,
        public string $target = '',
    ) {}

    /**
     * Create from an ACF link field array.
     *
     * @param array<string, mixed>|null $link ACF link field value
     */
    public static function fromAcf(?array $link): ?self
    {
        if ($link === null || empty($link['url'])) {
            return null;
        }

        return new self(url: $link['url'], title: $link['title'] ?? '', target: $link['target'] ?? '');
    }
}
