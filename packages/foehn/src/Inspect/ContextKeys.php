<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use PhpToken;
use ReflectionClass;

/**
 * The Twig variables a template can count on, and the ones a class adds, found
 * without rendering anything.
 *
 * Two kinds, kept apart because they promise different things. Base keys are set
 * by Føhn and Timber on every render, whatever the project code does. Declared keys
 * are read from the source of a controller or a context provider: each literal
 * `->with('key', …)` call in the class. Such a call can sit in a branch, so a
 * declared key is not guaranteed at runtime, and a key the class names at runtime
 * (`with($name, …)`, `merge()`, `withDto()`) cannot be listed at all: the class is
 * then reported as partial.
 */
final class ContextKeys
{
    /**
     * Keys every `ViewEngine::render()` call passes to Twig: the TemplateContext
     * properties, and the global context of `Timber::context_global()`.
     *
     * @var list<string>
     */
    private const array RENDER = ['body_class', 'http_host', 'post', 'posts', 'site', 'theme', 'user', 'wp_title'];

    /**
     * The calls on a TemplateContext that add keys.
     *
     * @var list<string>
     */
    private const array ADDING_METHODS = ['with', 'merge', 'withDto'];

    /**
     * Keys every view rendered through the view engine receives.
     *
     * @param bool $hasMenus Whether the project declares a #[AsMenu]: MenuDiscovery
     *   then adds `menus` to the global context
     * @return list<string>
     */
    public static function render(bool $hasMenus): array
    {
        return self::sorted([...self::RENDER, ...($hasMenus ? ['menus'] : [])]);
    }

    /**
     * Keys the TemplateContext holds when a controller receives it for a template.
     *
     * The render keys, plus what `Timber::context()` adds for the request that
     * template answers: the queried term on a term archive, the search string on a
     * search page, the author on an author archive. A wildcard that reaches several
     * kinds of request adds none of them.
     *
     * @return list<string>
     */
    public static function controller(string $template, bool $hasMenus): array
    {
        $request = match (true) {
            self::answers($template, 'category'),
            self::answers($template, 'tag'),
            self::answers($template, 'taxonomy'),
                => ['term'],
            $template === 'search' => ['search_query'],
            self::answers($template, 'author') => ['author'],
            default => [],
        };

        return self::sorted([...self::render($hasMenus), ...$request]);
    }

    /**
     * The keys a class adds with literal `->with()` calls, and whether it also adds
     * keys that only exist at runtime.
     *
     * @param class-string $className
     * @return array{keys: list<string>, partial: bool}
     */
    public static function declaredBy(string $className): array
    {
        $class = new ReflectionClass($className);
        $file = $class->getFileName();

        if ($file === false || !is_readable($file)) {
            return ['keys' => [], 'partial' => true];
        }

        $start = (int) $class->getStartLine();
        $end = (int) $class->getEndLine();
        $tokens = array_values(array_filter(
            PhpToken::tokenize((string) file_get_contents($file)),
            static fn(PhpToken $piece): bool => (
                !$piece->isIgnorable()
                && $piece->line >= $start
                && $piece->line <= $end
            ),
        ));

        $keys = [];
        $partial = false;

        foreach ($tokens as $index => $token) {
            if (!$token->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
                continue;
            }

            $method = $tokens[$index + 1] ?? null;

            if (
                $method === null
                || !$method->is(T_STRING)
                || !in_array($method->text, self::ADDING_METHODS, true)
                || ($tokens[$index + 2] ?? null)?->text !== '('
            ) {
                continue;
            }

            $argument = $tokens[$index + 3] ?? null;
            $next = $tokens[$index + 4] ?? null;

            if (
                $method->text === 'with'
                && $argument !== null
                && $argument->is(T_CONSTANT_ENCAPSED_STRING)
                && $next?->text === ','
            ) {
                $keys[] = self::unquote($argument->text);

                continue;
            }

            $partial = true;
        }

        return ['keys' => self::sorted($keys), 'partial' => $partial];
    }

    /**
     * Whether a template name or pattern only reaches one kind of request: the
     * name itself, or the name followed by `-`.
     */
    private static function answers(string $template, string $kind): bool
    {
        return $template === $kind || str_starts_with($template, $kind . '-');
    }

    private static function unquote(string $literal): string
    {
        $body = substr($literal, 1, -1);

        return $literal[0] === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $body) : stripcslashes($body);
    }

    /**
     * @param array<string> $keys
     * @return list<string>
     */
    private static function sorted(array $keys): array
    {
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}
