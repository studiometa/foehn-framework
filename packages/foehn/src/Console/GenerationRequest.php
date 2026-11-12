<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

use Tempest\Discovery\Discovery;

/**
 * What a `make:` command wants generated, described rather than performed.
 *
 * A stub is a real, compilable class carrying real attributes, so the generator
 * rewrites it structurally: attribute arguments are set by name, never by matching
 * the formatting of the printed source. Only the parts of a stub that are code
 * fragments rather than values — a field builder chain, a template list — are still
 * substituted textually, and those are scoped to the method that holds them.
 *
 * Where the class goes is the command's default until ProjectConventions places it
 * next to the classes of the same kind the project already declares: `discovery`
 * names that kind.
 */
final readonly class GenerationRequest
{
    /**
     * @param class-string $stub Stub class to copy
     * @param string $subdirectory Subdirectory of the app path (e.g. 'Blocks', 'Models')
     * @param string $className Class name of the generated file, without extension
     * @param array<string, mixed> $attributeArguments Named arguments merged onto the stub's class attribute
     * @param array<string, array<string, string>> $bodyReplacements Method name => sentinel => replacement
     * @param array<string, string> $replacements Sentinel => replacement, anywhere in the printed file
     * @param class-string<Discovery>|null $discovery The discovery that collects classes of this kind
     * @param string|null $namespace The namespace, or null to read it from the composer PSR-4 map
     * @param class-string|null $parent The parent class, or null to keep the stub's
     * @param list<array{name: class-string, arguments: array<string, mixed>}> $attributes Class attributes
     *   added after the stub's own, in order
     * @param array<string, array{returnType: string, body: string}> $methods Public methods added to the class,
     *   by name
     */
    public function __construct(
        public string $stub,
        public string $subdirectory,
        public string $className,
        public array $attributeArguments = [],
        public array $bodyReplacements = [],
        public array $replacements = [],
        public ?string $discovery = null,
        public ?string $namespace = null,
        public ?string $parent = null,
        public array $attributes = [],
        public array $methods = [],
    ) {}

    /**
     * The same request, put in another directory and namespace, under another parent.
     *
     * @param class-string|null $parent
     */
    public function placedIn(string $subdirectory, string $namespace, ?string $parent): self
    {
        return new self(
            stub: $this->stub,
            subdirectory: $subdirectory,
            className: $this->className,
            attributeArguments: $this->attributeArguments,
            bodyReplacements: $this->bodyReplacements,
            replacements: $this->replacements,
            discovery: $this->discovery,
            namespace: $namespace,
            parent: $parent,
            attributes: $this->attributes,
            methods: $this->methods,
        );
    }
}
