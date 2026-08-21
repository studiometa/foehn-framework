<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use Studiometa\Foehn\Contracts\ImageTransformer;

/**
 * The transformer of a project that does not transform images.
 *
 * Returns every URL untouched, so a template written against the interface
 * renders correctly with no provider configured. This is the default: adding the
 * abstraction should change nothing until a project asks it to.
 */
final class NullTransformer implements ImageTransformer
{
    /**
     * @param array<string, string|int> $transform
     */
    public function url(string $src, array $transform): string
    {
        return $src;
    }

    public function forget(string $src): void {}
}
