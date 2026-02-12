<?php

declare(strict_types=1);

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

// Test fixtures
final readonly class SimpleDto implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $title,
        public int $count,
    ) {}
}

final readonly class CamelCaseDto implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $imageUrl,
    ) {}
}

final readonly class NestedDto implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $heading,
        public ?SimpleDto $child = null,
    ) {}
}

final readonly class ArrayOfArrayablesDto implements Arrayable
{
    use HasToArray;

    /** @param list<SimpleDto> $items */
    public function __construct(
        public string $section,
        public array $items = [],
    ) {}
}

final readonly class NullableDto implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $required,
        public ?string $optional = null,
        public ?SimpleDto $child = null,
    ) {}
}

final readonly class CustomKeyDto implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $myProperty,
    ) {}

    protected function propertyToKey(string $name): string
    {
        return $name; // keep camelCase
    }
}

describe('HasToArray', function () {
    it('converts simple properties to array', function () {
        $dto = new SimpleDto(title: 'Hello', count: 42);

        expect($dto->toArray())->toBe([
            'title' => 'Hello',
            'count' => 42,
        ]);
    });

    it('converts camelCase properties to snake_case keys', function () {
        $dto = new CamelCaseDto(firstName: 'John', lastName: 'Doe', imageUrl: 'https://example.com/img.jpg');

        expect($dto->toArray())->toBe([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'image_url' => 'https://example.com/img.jpg',
        ]);
    });

    it('recursively converts nested Arrayable objects', function () {
        $dto = new NestedDto(heading: 'Section', child: new SimpleDto(title: 'Nested', count: 1));

        expect($dto->toArray())->toBe([
            'heading' => 'Section',
            'child' => [
                'title' => 'Nested',
                'count' => 1,
            ],
        ]);
    });

    it('handles null nested objects', function () {
        $dto = new NestedDto(heading: 'Alone');

        expect($dto->toArray())->toBe([
            'heading' => 'Alone',
            'child' => null,
        ]);
    });

    it('recursively converts arrays of Arrayable objects', function () {
        $dto = new ArrayOfArrayablesDto(section: 'Products', items: [
            new SimpleDto(title: 'A', count: 1),
            new SimpleDto(title: 'B', count: 2),
        ]);

        expect($dto->toArray())->toBe([
            'section' => 'Products',
            'items' => [
                ['title' => 'A', 'count' => 1],
                ['title' => 'B', 'count' => 2],
            ],
        ]);
    });

    it('handles nullable properties', function () {
        $dto = new NullableDto(required: 'yes');

        expect($dto->toArray())->toBe([
            'required' => 'yes',
            'optional' => null,
            'child' => null,
        ]);
    });

    it('allows overriding propertyToKey for custom mapping', function () {
        $dto = new CustomKeyDto(myProperty: 'value');

        expect($dto->toArray())->toBe([
            'myProperty' => 'value',
        ]);
    });

    it('handles empty arrays', function () {
        $dto = new ArrayOfArrayablesDto(section: 'Empty', items: []);

        expect($dto->toArray())->toBe([
            'section' => 'Empty',
            'items' => [],
        ]);
    });

    it('handles mixed arrays with Arrayable and scalar values', function () {
        $dto = new ArrayOfArrayablesDto(section: 'Mixed', items: [
            new SimpleDto(title: 'A', count: 1),
            'string_value',
            42,
        ]);

        $result = $dto->toArray();

        expect($result['items'][0])->toBe(['title' => 'A', 'count' => 1]);
        expect($result['items'][1])->toBe('string_value');
        expect($result['items'][2])->toBe(42);
    });
});
