<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

/**
 * What a `make:` command wants generated, described rather than performed.
 *
 * A stub is a real, compilable class carrying real attributes, so the generator
 * rewrites it structurally: attribute arguments are set by name, never by matching
 * the formatting of the printed source. Only the parts of a stub that are code
 * fragments rather than values — a field builder chain, a template list — are still
 * substituted textually, and those are scoped to the method that holds them.
 */
final readonly class GenerationRequest
{
    /**
     * @param class-string $stub Stub class to copy
     * @param string $subdirectory Subdirectory of the app path (e.g. 'Blocks', 'PostTypes')
     * @param string $className Class name of the generated file, without extension
     * @param array<string, mixed> $attributeArguments Named arguments merged onto the stub's class attribute
     * @param array<string, array<string, string>> $bodyReplacements Method name => sentinel => replacement
     * @param array<string, string> $replacements Sentinel => replacement, anywhere in the printed file
     */
    public function __construct(
        public string $stub,
        public string $subdirectory,
        public string $className,
        public array $attributeArguments = [],
        public array $bodyReplacements = [],
        public array $replacements = [],
    ) {}
}
