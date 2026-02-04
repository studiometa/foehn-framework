<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\Concerns\ValidatesFields;

// Create a test class that uses the trait
final class ValidatesFieldsTestClass
{
    use ValidatesFields;

    // Expose protected methods for testing
    public function testValidateRequired(array $fields, array $required): void
    {
        $this->validateRequired($fields, $required);
    }

    public function testValidateType(mixed $value, string $expectedType): bool
    {
        return $this->validateType($value, $expectedType);
    }

    public function testSanitizeField(mixed $value, string $type): mixed
    {
        return $this->sanitizeField($value, $type);
    }

    public function testValidateFields(array $fields, array $schema): array
    {
        return $this->validateFields($fields, $schema);
    }
}

describe('ValidatesFields::validateRequired', function () {
    beforeEach(function () {
        $this->validator = new ValidatesFieldsTestClass();
    });

    it('passes when all required fields are present', function () {
        $fields = ['title' => 'Hello', 'content' => 'World'];

        $this->validator->testValidateRequired($fields, ['title', 'content']);

        expect(true)->toBeTrue(); // No exception thrown
    });

    it('throws when a required field is missing', function () {
        $fields = ['title' => 'Hello'];

        $this->validator->testValidateRequired($fields, ['title', 'content']);
    })->throws(InvalidArgumentException::class, 'Required field "content" is missing.');

    it('throws when a required field is empty string', function () {
        $fields = ['title' => ''];

        $this->validator->testValidateRequired($fields, ['title']);
    })->throws(InvalidArgumentException::class, 'Required field "title" cannot be empty.');

    it('throws when a required field is empty array', function () {
        $fields = ['items' => []];

        $this->validator->testValidateRequired($fields, ['items']);
    })->throws(InvalidArgumentException::class, 'Required field "items" cannot be empty.');

    it('throws when a required field is null', function () {
        $fields = ['title' => null];

        $this->validator->testValidateRequired($fields, ['title']);
    })->throws(InvalidArgumentException::class, 'Required field "title" cannot be empty.');

    it('passes with empty required list', function () {
        $fields = [];

        $this->validator->testValidateRequired($fields, []);

        expect(true)->toBeTrue();
    });
});

describe('ValidatesFields::validateType', function () {
    beforeEach(function () {
        $this->validator = new ValidatesFieldsTestClass();
    });

    it('validates string type', function () {
        expect($this->validator->testValidateType('hello', 'string'))->toBeTrue();
        expect($this->validator->testValidateType(123, 'string'))->toBeFalse();
        expect($this->validator->testValidateType(null, 'string'))->toBeFalse();
    });

    it('validates int type', function () {
        expect($this->validator->testValidateType(123, 'int'))->toBeTrue();
        expect($this->validator->testValidateType(123, 'integer'))->toBeTrue();
        expect($this->validator->testValidateType('123', 'int'))->toBeFalse();
        expect($this->validator->testValidateType(12.5, 'int'))->toBeFalse();
    });

    it('validates float type', function () {
        expect($this->validator->testValidateType(12.5, 'float'))->toBeTrue();
        expect($this->validator->testValidateType(12.5, 'double'))->toBeTrue();
        expect($this->validator->testValidateType(123, 'float'))->toBeTrue(); // int is valid float
        expect($this->validator->testValidateType('12.5', 'float'))->toBeFalse();
    });

    it('validates bool type', function () {
        expect($this->validator->testValidateType(true, 'bool'))->toBeTrue();
        expect($this->validator->testValidateType(false, 'boolean'))->toBeTrue();
        expect($this->validator->testValidateType(1, 'bool'))->toBeFalse();
        expect($this->validator->testValidateType('true', 'bool'))->toBeFalse();
    });

    it('validates array type', function () {
        expect($this->validator->testValidateType([], 'array'))->toBeTrue();
        expect($this->validator->testValidateType(['a', 'b'], 'array'))->toBeTrue();
        expect($this->validator->testValidateType('not array', 'array'))->toBeFalse();
    });

    it('validates object type', function () {
        expect($this->validator->testValidateType(new stdClass(), 'object'))->toBeTrue();
        expect($this->validator->testValidateType([], 'object'))->toBeFalse();
    });

    it('validates numeric type', function () {
        expect($this->validator->testValidateType(123, 'numeric'))->toBeTrue();
        expect($this->validator->testValidateType('123', 'numeric'))->toBeTrue();
        expect($this->validator->testValidateType('12.5', 'numeric'))->toBeTrue();
        expect($this->validator->testValidateType('abc', 'numeric'))->toBeFalse();
    });

    it('validates class instance', function () {
        expect($this->validator->testValidateType(new stdClass(), stdClass::class))->toBeTrue();
        expect($this->validator->testValidateType(new DateTime(), DateTimeInterface::class))->toBeTrue();
        expect($this->validator->testValidateType('not object', stdClass::class))->toBeFalse();
    });

    it('validates iterable type', function () {
        expect($this->validator->testValidateType([], 'iterable'))->toBeTrue();
        expect($this->validator->testValidateType(new ArrayIterator(), 'iterable'))->toBeTrue();
        expect($this->validator->testValidateType('string', 'iterable'))->toBeFalse();
    });
});

describe('ValidatesFields::sanitizeField', function () {
    beforeEach(function () {
        $this->validator = new ValidatesFieldsTestClass();
    });

    it('sanitizes to string', function () {
        expect($this->validator->testSanitizeField('hello', 'string'))->toBe('hello');
        expect($this->validator->testSanitizeField('  trimmed  ', 'string'))->toBe('trimmed');
        expect($this->validator->testSanitizeField(123, 'string'))->toBe('123');
        expect($this->validator->testSanitizeField(true, 'string'))->toBe('1');
        expect($this->validator->testSanitizeField(null, 'string'))->toBe('');
    });

    it('sanitizes to int', function () {
        expect($this->validator->testSanitizeField(123, 'int'))->toBe(123);
        expect($this->validator->testSanitizeField('456', 'int'))->toBe(456);
        expect($this->validator->testSanitizeField(12.9, 'int'))->toBe(12);
        expect($this->validator->testSanitizeField(true, 'int'))->toBe(1);
        expect($this->validator->testSanitizeField(false, 'int'))->toBe(0);
        expect($this->validator->testSanitizeField(null, 'int'))->toBe(0);
        expect($this->validator->testSanitizeField('abc', 'int'))->toBe(0);
    });

    it('sanitizes to float', function () {
        expect($this->validator->testSanitizeField(12.5, 'float'))->toBe(12.5);
        expect($this->validator->testSanitizeField('12.5', 'float'))->toBe(12.5);
        expect($this->validator->testSanitizeField(123, 'float'))->toBe(123.0);
        expect($this->validator->testSanitizeField(null, 'float'))->toBe(0.0);
    });

    it('sanitizes to bool', function () {
        expect($this->validator->testSanitizeField(true, 'bool'))->toBeTrue();
        expect($this->validator->testSanitizeField(false, 'bool'))->toBeFalse();
        expect($this->validator->testSanitizeField('true', 'bool'))->toBeTrue();
        expect($this->validator->testSanitizeField('yes', 'bool'))->toBeTrue();
        expect($this->validator->testSanitizeField('1', 'bool'))->toBeTrue();
        expect($this->validator->testSanitizeField('on', 'bool'))->toBeTrue();
        expect($this->validator->testSanitizeField('false', 'bool'))->toBeFalse();
        expect($this->validator->testSanitizeField('no', 'bool'))->toBeFalse();
        expect($this->validator->testSanitizeField(1, 'bool'))->toBeTrue();
        expect($this->validator->testSanitizeField(0, 'bool'))->toBeFalse();
        expect($this->validator->testSanitizeField(null, 'bool'))->toBeFalse();
    });

    it('sanitizes to array', function () {
        expect($this->validator->testSanitizeField(['a', 'b'], 'array'))->toBe(['a', 'b']);
        expect($this->validator->testSanitizeField(null, 'array'))->toBe([]);
        expect($this->validator->testSanitizeField('string', 'array'))->toBe([]);
    });

    it('sanitizes HTML content', function () {
        $html = '<p>Hello <script>alert("xss")</script> World</p>';
        $result = $this->validator->testSanitizeField($html, 'html');

        expect($result)->toContain('<p>');
        expect($result)->not->toContain('<script>');
    });

    it('sanitizes email', function () {
        expect($this->validator->testSanitizeField('test@example.com', 'email'))->toBe('test@example.com');
        expect($this->validator->testSanitizeField('  TEST@EXAMPLE.COM  ', 'email'))->toBe('TEST@EXAMPLE.COM');
        expect($this->validator->testSanitizeField(null, 'email'))->toBe('');
    });

    it('sanitizes URL', function () {
        expect($this->validator->testSanitizeField('https://example.com', 'url'))->toBe('https://example.com');
        expect($this->validator->testSanitizeField('  https://example.com  ', 'url'))->toBe('https://example.com');
        expect($this->validator->testSanitizeField(null, 'url'))->toBe('');
    });

    it('returns value unchanged for unknown type', function () {
        $object = new stdClass();
        expect($this->validator->testSanitizeField($object, 'unknown'))->toBe($object);
    });
});

describe('ValidatesFields::validateFields', function () {
    beforeEach(function () {
        $this->validator = new ValidatesFieldsTestClass();
    });

    it('validates and sanitizes fields according to schema', function () {
        $fields = [
            'title' => '  Hello World  ',
            'count' => '42',
            'active' => 'yes',
        ];

        $schema = [
            'title' => ['type' => 'string', 'required' => true],
            'count' => ['type' => 'int'],
            'active' => ['type' => 'bool'],
        ];

        $result = $this->validator->testValidateFields($fields, $schema);

        expect($result['title'])->toBe('Hello World');
        expect($result['count'])->toBe(42);
        expect($result['active'])->toBeTrue();
    });

    it('throws on missing required field', function () {
        $fields = ['title' => 'Hello'];

        $schema = [
            'title' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'string', 'required' => true],
        ];

        $this->validator->testValidateFields($fields, $schema);
    })->throws(InvalidArgumentException::class, 'Required field "content" is missing or empty.');

    it('uses default values for missing optional fields', function () {
        $fields = ['title' => 'Hello'];

        $schema = [
            'title' => ['type' => 'string', 'required' => true],
            'count' => ['type' => 'int', 'default' => 10],
            'tags' => ['type' => 'array', 'default' => ['default']],
        ];

        $result = $this->validator->testValidateFields($fields, $schema);

        expect($result['title'])->toBe('Hello');
        expect($result['count'])->toBe(10);
        expect($result['tags'])->toBe(['default']);
    });

    it('uses type-appropriate defaults when no default specified', function () {
        $fields = [];

        $schema = [
            'text' => ['type' => 'string'],
            'number' => ['type' => 'int'],
            'decimal' => ['type' => 'float'],
            'flag' => ['type' => 'bool'],
            'items' => ['type' => 'array'],
        ];

        $result = $this->validator->testValidateFields($fields, $schema);

        expect($result['text'])->toBe('');
        expect($result['number'])->toBe(0);
        expect($result['decimal'])->toBe(0.0);
        expect($result['flag'])->toBeFalse();
        expect($result['items'])->toBe([]);
    });

    it('handles empty string as missing for required fields', function () {
        $fields = ['title' => ''];

        $schema = [
            'title' => ['type' => 'string', 'required' => true],
        ];

        $this->validator->testValidateFields($fields, $schema);
    })->throws(InvalidArgumentException::class, 'Required field "title" is missing or empty.');

    it('handles null as missing for required fields', function () {
        $fields = ['title' => null];

        $schema = [
            'title' => ['type' => 'string', 'required' => true],
        ];

        $this->validator->testValidateFields($fields, $schema);
    })->throws(InvalidArgumentException::class, 'Required field "title" is missing or empty.');

    it('handles complex schema with mixed requirements', function () {
        $fields = [
            'title' => 'My Block',
            'subtitle' => '',
            'image' => ['url' => 'https://example.com/img.jpg', 'alt' => 'Image'],
        ];

        $schema = [
            'title' => ['type' => 'string', 'required' => true],
            'subtitle' => ['type' => 'string', 'default' => 'Default subtitle'],
            'description' => ['type' => 'string'],
            'image' => ['type' => 'array'],
            'count' => ['type' => 'int', 'default' => 0],
        ];

        $result = $this->validator->testValidateFields($fields, $schema);

        expect($result['title'])->toBe('My Block');
        expect($result['subtitle'])->toBe('Default subtitle'); // Empty string triggers default
        expect($result['description'])->toBe(''); // No default, uses type default
        expect($result['image'])->toBe(['url' => 'https://example.com/img.jpg', 'alt' => 'Image']);
        expect($result['count'])->toBe(0);
    });
});
