<?php

declare(strict_types=1);

use Studiometa\Foehn\Jobs\JobSerializer;
use Tests\Fixtures\JobDtoFixture;

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
    });
});
