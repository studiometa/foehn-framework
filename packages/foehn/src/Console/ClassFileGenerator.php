<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use RuntimeException;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Generation\Php\ClassManipulator;
use Tempest\Support\Filesystem;

use function Tempest\Support\str;

/**
 * Turns a stub class into a file in the app directory.
 *
 * The app path is a constructor dependency rather than something reached for at call
 * time, so a caller — including a test — decides where generation lands.
 *
 * Namespace resolution, attribute rewriting and stub substitution all live behind
 * generate(), which returns the file rather than writing it. Writing is a separate
 * call, so a command that only wants to show its output never touches the disk.
 */
final class ClassFileGenerator
{
    public function __construct(
        private readonly string $appPath,
    ) {}

    /**
     * Build the file a request describes.
     *
     * @throws RuntimeException If a substitution sentinel is not present in the stub
     */
    public function generate(GenerationRequest $request): GeneratedFile
    {
        $path = $this->targetPath($request->subdirectory, $request->className);

        $manipulator = new ClassManipulator($request->stub);
        $manipulator
            ->setStrictTypes()
            ->setNamespace($this->resolveNamespace($path))
            ->setClassName($request->className)
            ->removeClassAttribute(SkipDiscovery::class);

        $this->rewriteClassAttribute($manipulator, $request);
        $this->replaceInBodies($manipulator, $request);

        return new GeneratedFile($path, $this->applyReplacements($manipulator->print(), $request));
    }

    /**
     * Write a generated file, unless it already exists and $force is false.
     *
     * @return bool Whether the file was written
     */
    public function write(GeneratedFile $file, bool $force = false): bool
    {
        if (Filesystem\is_file($file->path) && !$force) {
            return false;
        }

        $directory = dirname($file->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        Filesystem\write_file($file->path, $file->contents);

        return true;
    }

    /**
     * Get the path a request would be written to.
     */
    public function targetPath(string $subdirectory, string $className): string
    {
        $segments = array_filter([rtrim($this->appPath, '/'), trim($subdirectory, '/'), $className . '.php']);

        return implode('/', $segments);
    }

    /**
     * Replace the stub's class attribute with one carrying the request's arguments.
     *
     * The stub's own arguments are the defaults, read from the compiled attribute
     * rather than from its source, so the printed formatting is irrelevant.
     */
    private function rewriteClassAttribute(ClassManipulator $manipulator, GenerationRequest $request): void
    {
        if ($request->attributeArguments === []) {
            return;
        }

        $attribute = $this->classAttribute($request->stub);

        if ($attribute === null) {
            throw new RuntimeException(sprintf(
                'Stub %s carries no class attribute to configure, but %d argument(s) were given.',
                $request->stub,
                count($request->attributeArguments),
            ));
        }

        $unknown = array_diff(array_keys($request->attributeArguments), self::parameterNames($attribute->getName()));

        if ($unknown !== []) {
            throw new RuntimeException(sprintf(
                '#[%s] has no argument(s) named %s.',
                $attribute->getName(),
                implode(', ', $unknown),
            ));
        }

        $manipulator->removeClassAttribute($attribute->getName())->addClassAttribute($attribute->getName(), [
            ...$attribute->getArguments(),
            ...$request->attributeArguments,
        ]);
    }

    /**
     * List the argument names an attribute accepts.
     *
     * The attribute's constructor is the authority, not the arguments the stub happens
     * to have written, so a command may set an argument the stub leaves at its default
     * while a misspelled name still fails here rather than being silently dropped.
     *
     * @param class-string $attributeClass
     * @return list<string>
     */
    private static function parameterNames(string $attributeClass): array
    {
        $constructor = new ReflectionClass($attributeClass)->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return array_values(array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
    }

    /**
     * Find the stub's own class attribute, ignoring the discovery opt-out.
     *
     * @param class-string $stub
     * @return ReflectionAttribute<object>|null
     */
    private function classAttribute(string $stub): ?ReflectionAttribute
    {
        /** @var ReflectionAttribute<object> $attribute */
        foreach (new ReflectionClass($stub)->getAttributes() as $attribute) {
            if ($attribute->getName() === SkipDiscovery::class) {
                continue;
            }

            return $attribute;
        }

        return null;
    }

    /**
     * Substitute code fragments inside the bodies of named methods.
     *
     * A sentinel that is absent means the stub changed under the command, which would
     * otherwise generate a file still carrying the stub's placeholder value.
     */
    private function replaceInBodies(ClassManipulator $manipulator, GenerationRequest $request): void
    {
        if ($request->bodyReplacements === []) {
            return;
        }

        $manipulator->manipulate(function (mixed $code) use ($request): string {
            $source = (string) $code;

            foreach ($request->bodyReplacements as $method => $replacements) {
                $source = $this->replaceInMethodBody($source, $method, $replacements, $request->stub);
            }

            return $source;
        });
    }

    /**
     * Replace sentinels within the source of a single method.
     *
     * @param array<string, string> $replacements
     * @param class-string $stub
     */
    private function replaceInMethodBody(string $source, string $method, array $replacements, string $stub): string
    {
        $start = strpos($source, "function {$method}(");

        if ($start === false) {
            throw new RuntimeException(sprintf('Stub %s has no method %s() to substitute into.', $stub, $method));
        }

        $before = substr($source, 0, $start);
        $body = substr($source, $start);

        foreach ($replacements as $sentinel => $replacement) {
            if (!str_contains($body, $sentinel)) {
                throw new RuntimeException(sprintf(
                    'Stub %s::%s() no longer contains the fragment %s. '
                    . 'The stub and the command that substitutes into it are out of step.',
                    $stub,
                    $method,
                    var_export($sentinel, true),
                ));
            }

            $body = str_replace($sentinel, $replacement, $body);
        }

        return $before . $body;
    }

    /**
     * Apply the whole-file sentinels, each of which must be present.
     */
    private function applyReplacements(string $content, GenerationRequest $request): string
    {
        foreach ($request->replacements as $sentinel => $replacement) {
            if (!str_contains($content, $sentinel)) {
                throw new RuntimeException(sprintf(
                    'Stub %s no longer contains the sentinel %s. '
                    . 'The stub and the command that substitutes into it are out of step.',
                    $request->stub,
                    var_export($sentinel, true),
                ));
            }

            $content = str_replace($sentinel, $replacement, $content);
        }

        return $content;
    }

    /**
     * Resolve the namespace a generated file belongs in, from its path.
     */
    private function resolveNamespace(string $filePath): string
    {
        $relativePath = trim(str_replace($this->appPath, '', dirname($filePath)), '/');
        $baseNamespace = $this->findBaseNamespace();

        if ($relativePath === '') {
            return $baseNamespace;
        }

        $segments = array_map(
            static fn(string $segment): string => str($segment)->pascal()->toString(),
            explode('/', $relativePath),
        );

        return $baseNamespace . '\\' . implode('\\', $segments);
    }

    /**
     * Find the base namespace of the app path from the nearest composer.json.
     */
    private function findBaseNamespace(): string
    {
        $composerPath = $this->findComposerJson();

        if ($composerPath === null) {
            return 'App';
        }

        /** @var array{autoload?: array{psr-4?: array<string, string>}} $composer */
        $composer = json_decode(Filesystem\read_file($composerPath), true);
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        // Prefer the namespace that maps to the app path itself
        foreach ($psr4 as $namespace => $path) {
            $fullPath = dirname($composerPath) . '/' . rtrim($path, '/');

            if (realpath($fullPath) === realpath($this->appPath)) {
                return rtrim($namespace, '\\');
            }
        }

        if ($psr4 !== []) {
            return rtrim(array_key_first($psr4), '\\');
        }

        return 'App';
    }

    /**
     * Find composer.json by walking up from the app path.
     */
    private function findComposerJson(): ?string
    {
        $current = $this->appPath;
        $root = dirname($current, 10); // Safety limit

        while ($current !== $root && $current !== '/' && $current !== '.') {
            $composerPath = $current . '/composer.json';

            if (Filesystem\is_file($composerPath)) {
                return $composerPath;
            }

            $current = dirname($current);
        }

        return null;
    }
}
