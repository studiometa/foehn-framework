<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

/**
 * DTO for spacing fields.
 *
 * Matches the output of SpacingBuilder fields.
 */
final readonly class SpacingData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $top = 'medium',
        public string $bottom = 'medium',
    ) {}

    /**
     * Create from ACF field values.
     *
     * @param array<string, mixed>|null $fields ACF fields array
     * @param string $prefix Field name prefix (matching SpacingBuilder name)
     */
    public static function fromAcf(?array $fields, string $prefix = 'spacing'): self
    {
        return new self(top: $fields[$prefix . '_top'] ?? 'medium', bottom: $fields[$prefix . '_bottom'] ?? 'medium');
    }
}
