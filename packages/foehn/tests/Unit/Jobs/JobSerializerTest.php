<?php

declare(strict_types=1);

use Studiometa\Foehn\Jobs\JobSerializer;
use Tests\Fixtures\JobDtoFixture;
use Tests\Fixtures\JobDtoNoConstructorFixture;
use Tests\Fixtures\JobDtoWithDefaultsFixture;

describe('JobSerializer', function () {
    describe('serialize', function () {
        it('serializes a DTO to an array', function () {
            $job = new JobDtoFixture(42, 'csv');
            $payload = JobSerializer::serialize($job);

            expect($payload)->toBe([
                '__class' => JobDtoFixture::class,
                '__data' => [
                    'importId' => 42,
                    'source' => 'csv',
                ],
            ]);
        });

        it('rejects non-serializable properties', function () {
            $job = new class {
                public \stdClass $obj;

                public function __construct()
                {
                    $this->obj = new \stdClass();
                }
            };

            JobSerializer::serialize($job);
        })->throws(InvalidArgumentException::class, 'not serializable');

        it('handles arrays in properties', function () {
            $job = new class {
                public array $tags = ['php', 'wordpress'];
            };

            $payload = JobSerializer::serialize($job);

            expect($payload['__data']['tags'])->toBe(['php', 'wordpress']);
        });

        it('handles null values', function () {
            $job = new class {
                public ?string $value = null;
            };

            $payload = JobSerializer::serialize($job);

            expect($payload['__data']['value'])->toBeNull();
        });
    });

    describe('deserialize', function () {
        it('deserializes an array back to a DTO', function () {
            $payload = [
                '__class' => JobDtoFixture::class,
                '__data' => [
                    'importId' => 42,
                    'source' => 'csv',
                ],
            ];

            $job = JobSerializer::deserialize($payload);

            expect($job)->toBeInstanceOf(JobDtoFixture::class);
            expect($job->importId)->toBe(42);
            expect($job->source)->toBe('csv');
        });

        it('roundtrips serialize/deserialize', function () {
            $original = new JobDtoFixture(99, 'api');
            $payload = JobSerializer::serialize($original);
            $restored = JobSerializer::deserialize($payload);

            expect($restored)->toBeInstanceOf(JobDtoFixture::class);
            expect($restored->importId)->toBe(99);
            expect($restored->source)->toBe('api');
        });

        it('casts types correctly', function () {
            $payload = [
                '__class' => JobDtoFixture::class,
                '__data' => [
                    'importId' => '42',
                    'source' => 'csv',
                ],
            ];

            $job = JobSerializer::deserialize($payload);

            expect($job->importId)->toBe(42);
            expect($job->importId)->toBeInt();
        });

        it('throws on missing class', function () {
            $payload = [
                '__class' => 'NonExistent\\Class',
                '__data' => [],
            ];

            JobSerializer::deserialize($payload);
        })->throws(InvalidArgumentException::class, 'does not exist');

        it('throws on invalid payload format', function () {
            JobSerializer::deserialize(['foo' => 'bar']);
        })->throws(InvalidArgumentException::class, 'missing __class or __data');

        it('throws on missing required parameter', function () {
            $payload = [
                '__class' => JobDtoFixture::class,
                '__data' => [
                    'importId' => 42,
                    // 'source' is missing
                ],
            ];

            JobSerializer::deserialize($payload);
        })->throws(InvalidArgumentException::class, "Missing required parameter 'source'");

        it('handles class with no constructor', function () {
            $payload = [
                '__class' => JobDtoNoConstructorFixture::class,
                '__data' => [],
            ];

            $job = JobSerializer::deserialize($payload);

            expect($job)->toBeInstanceOf(JobDtoNoConstructorFixture::class);
        });

        it('uses default values for missing optional parameters', function () {
            $payload = [
                '__class' => JobDtoWithDefaultsFixture::class,
                '__data' => [
                    'id' => 1,
                    // 'format' and 'dryRun' are missing, should use defaults
                ],
            ];

            $job = JobSerializer::deserialize($payload);

            expect($job)->toBeInstanceOf(JobDtoWithDefaultsFixture::class);
            expect($job->id)->toBe(1);
            expect($job->format)->toBe('json');
            expect($job->dryRun)->toBeFalse();
        });

        it('casts bool values correctly', function () {
            $payload = [
                '__class' => JobDtoWithDefaultsFixture::class,
                '__data' => [
                    'id' => 5,
                    'format' => 'csv',
                    'dryRun' => 1,
                ],
            ];

            $job = JobSerializer::deserialize($payload);

            expect($job->dryRun)->toBeTrue();
            expect($job->dryRun)->toBeBool();
        });

        it('casts float values correctly', function () {
            $dto = new class(3.14) {
                public function __construct(
                    public float $value,
                ) {}
            };

            $payload = JobSerializer::serialize($dto);
            $restored = JobSerializer::deserialize($payload);

            expect($restored->value)->toBe(3.14);
            expect($restored->value)->toBeFloat();
        });

        it('passes through array values without casting', function () {
            $dto = new class([1, 2, 3]) {
                public function __construct(
                    public array $items,
                ) {}
            };

            $payload = JobSerializer::serialize($dto);
            $restored = JobSerializer::deserialize($payload);

            expect($restored->items)->toBe([1, 2, 3]);
        });

        it('passes through non-builtin typed parameter without casting', function () {
            // Test the castValue path where type is not builtin (object type)
            $innerDto = new \stdClass();
            $innerDto->foo = 'bar';

            $payload = [
                '__class' => get_class(new class(null) {
                    public function __construct(
                        public ?object $nested,
                    ) {}
                }),
                '__data' => ['nested' => null],
            ];

            $restored = JobSerializer::deserialize($payload);

            expect($restored->nested)->toBeNull();
        });

        it('handles nested arrays in serialization validation', function () {
            $dto = new class {
                public array $matrix = [[1, 2], [3, 4]];
            };

            $payload = JobSerializer::serialize($dto);

            expect($payload['__data']['matrix'])->toBe([[1, 2], [3, 4]]);
        });

        it('passes through value for untyped parameters', function () {
            $dto = new class('hello') {
                public function __construct(
                    public $value,
                ) {}
            };

            $payload = JobSerializer::serialize($dto);
            $restored = JobSerializer::deserialize($payload);

            expect($restored->value)->toBe('hello');
        });
    });
});
