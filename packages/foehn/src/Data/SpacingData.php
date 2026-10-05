<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

/**
 * DTO for spacing fields.
 *
 * Matches the `top` and `bottom` fields of SpacingBuilder.
 */
final readonly class SpacingData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $top = 'medium',
        public string $bottom = 'medium',
    ) {}

    /**
     * Create from the value of the ACF fields a SpacingBuilder adds.
     *
     * Pass the value of the group that holds the fragment, for example
     * `$fields['spacing']` after `addGroup('spacing')->addFields(new SpacingBuilder())`,
     * or the fields themselves when the fragment is not in a group.
     *
     * @param array<string, mixed>|null $spacing Value with the `top` and `bottom` keys
     */
    public static function fromAcf(?array $spacing): self
    {
        return new self(top: $spacing['top'] ?? 'medium', bottom: $spacing['bottom'] ?? 'medium');
    }
}
