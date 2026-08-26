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
    public const PARAMETER = 'sections';

    public const MAX_SECTIONS = 5;

    public const MAX_NAME_LENGTH = 64;

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

    public static function isSafeName(string $name): bool
    {
        return strlen($name) <= self::MAX_NAME_LENGTH && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) === 1;
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
        $invalid =
            count($names) > self::MAX_SECTIONS
            || count($names) !== count(array_unique($names))
            || array_any($names, static fn(string $name): bool => !self::isSafeName($name));

        return $invalid ? [true, false, [], 400] : [true, true, $names, 0];
    }

    /**
     * Return every raw value of an exact `sections` parameter.
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
