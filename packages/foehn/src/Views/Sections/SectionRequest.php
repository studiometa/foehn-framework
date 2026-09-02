<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Sections;

/**
 * The section selection carried by the current page request.
 *
 * Parsing the raw query string keeps repeated parameters visible. PHP would otherwise
 * keep only the last one in `$_GET`, which would make different cache readers disagree.
 */
final readonly class SectionRequest
{
    public const PARAMETER = 'foehn_sections';

    public const MAX_SECTIONS = 5;

    public const MAX_NAME_LENGTH = 64;

    /**
     * The grammar one section name follows, unanchored.
     *
     * A constant rather than a literal inside {@see SectionRequest::isSafeName()} because
     * the page cache has to state the same grammar to key `?foehn_sections=`, and a second
     * copy of it would drift. That has already cost this codebase one bug: a hand-written
     * copy of the cache's value charset stopped matching the original and made every
     * multi-value filter unstorable (see {@see \Studiometa\Foehn\PageCache\CacheKey::FILENAME_PATTERN}).
     */
    public const NAME_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * The whole parameter value, as the one pattern the page cache keys it with.
     *
     * Derived from {@see SectionRequest::NAME_PATTERN} and {@see SectionRequest::MAX_SECTIONS},
     * so what the cache stores is exactly what this class accepts.
     *
     * Two things it deliberately does not say, because neither can make it wrong:
     *
     * **The per-name length.** {@see SectionRequest::MAX_NAME_LENGTH} is 64 and
     * {@see \Studiometa\Foehn\PageCache\QueryKey::VALUE_MAX_LENGTH} caps the whole value at
     * 64, which is the stricter bound of the two.
     *
     * **Uniqueness.** `?foehn_sections=a,a` is a duplicate this parser refuses and a regex
     * cannot express. It is keyed, no file is ever written for it — the response is a 400
     * and only 200s are stored — so every reader misses and PHP answers with the 400.
     */
    public const VALUE_PATTERN =
        '^' . self::NAME_PATTERN . '(?:,' . self::NAME_PATTERN . '){0,' . (self::MAX_SECTIONS - 1) . '}$';

    private bool $selected;

    private bool $valid;

    private bool $head;

    /** @var list<string> */
    private array $names;

    private int $errorStatus;

    public function __construct(?string $method = null, ?string $requestUri = null)
    {
        $method = strtoupper($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri ??= $_SERVER['REQUEST_URI'] ?? '/';
        [$selected, $valid, $names, $errorStatus] = $this->parseSelection($method, $requestUri);

        $this->head = $method === 'HEAD';
        $this->selected = $selected;
        $this->valid = $valid;
        $this->names = $names;
        $this->errorStatus = $errorStatus;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isHead(): bool
    {
        return $this->head;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }

    public function errorStatus(): int
    {
        return $this->errorStatus;
    }

    /**
     * Run page code with the section control parameter hidden from WordPress helpers.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withoutControlParameter(callable $callback): mixed
    {
        $hadGetParameter = array_key_exists(self::PARAMETER, $_GET);
        $getParameter = $_GET[self::PARAMETER] ?? null;
        $hadRequestUri = array_key_exists('REQUEST_URI', $_SERVER);
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;

        unset($_GET[self::PARAMETER]);

        if (is_string($requestUri)) {
            $_SERVER['REQUEST_URI'] = $this->removeControlParameter($requestUri);
        }

        try {
            return $callback();
        } finally {
            unset($_GET[self::PARAMETER], $_SERVER['REQUEST_URI']);

            if ($hadGetParameter) {
                $_GET[self::PARAMETER] = $getParameter;
            }

            if ($hadRequestUri) {
                $_SERVER['REQUEST_URI'] = $requestUri;
            }
        }
    }

    public static function isSafeName(string $name): bool
    {
        return strlen($name) <= self::MAX_NAME_LENGTH && preg_match('/^' . self::NAME_PATTERN . '$/', $name) === 1;
    }

    /**
     * Whether a list of names is a selection this parser would accept.
     *
     * What a selection has to satisfy beyond the grammar of one name — at least one, no
     * more than {@see SectionRequest::MAX_SECTIONS}, no repeat — stated once and asked
     * twice. {@see \Studiometa\Foehn\Views\Twig\SectionExtension::url()} writes the
     * parameter that this class parses back, so a second copy of these rules would
     * eventually be a URL the helper is happy to build and the request answers 400 to.
     *
     * @param list<string> $names
     */
    public static function isSafeSelection(array $names): bool
    {
        return (
            $names !== []
            && count($names) <= self::MAX_SECTIONS
            && count($names) === count(array_unique($names))
            && !array_any($names, static fn(string $name): bool => !self::isSafeName($name))
        );
    }

    /**
     * @return array{bool, bool, list<string>, int}
     */
    private function parseSelection(string $method, string $requestUri): array
    {
        $values = $this->parameterValues($requestUri);

        if ($values === []) {
            return [false, true, [], 0];
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return [true, false, [], 405];
        }

        if (count($values) !== 1) {
            return [true, false, [], 400];
        }

        $names = explode(',', rawurldecode(str_replace('+', ' ', $values[0])));

        return self::isSafeSelection($names) ? [true, true, $names, 0] : [true, false, [], 400];
    }

    private function removeControlParameter(string $requestUri): string
    {
        [$uri, $fragment] = array_pad(explode('#', $requestUri, 2), 2, '');
        [$path, $query] = array_pad(explode('?', $uri, 2), 2, '');
        $pairs = array_values(array_filter(
            explode('&', $query),
            static fn(string $pair): bool => $pair !== '' && explode('=', $pair, 2)[0] !== self::PARAMETER,
        ));
        $uri = $path . ($pairs === [] ? '' : '?' . implode('&', $pairs));

        return $fragment === '' ? $uri : $uri . '#' . $fragment;
    }

    /**
     * Return every raw value of the exact section control parameter.
     *
     * @return list<string>
     */
    private function parameterValues(string $requestUri): array
    {
        $query = parse_url($requestUri, PHP_URL_QUERY);

        if (!is_string($query)) {
            return [];
        }

        $values = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if ($name === self::PARAMETER) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
