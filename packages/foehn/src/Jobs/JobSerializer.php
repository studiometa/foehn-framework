<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Jobs;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Serializes and deserializes job DTOs to/from arrays.
 *
 * Job DTOs must have readonly public properties with scalar or array types.
 * They are stored as JSON-compatible arrays in Action Scheduler.
 */
final class JobSerializer
{
    /**
     * Serialize a job DTO to an array suitable for Action Scheduler args.
     *
     * @return array{__class: class-string, __data: array<string, mixed>}
     */
    public static function serialize(object $job): array
    {
        $reflection = new ReflectionClass($job);
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($job);
            self::validateSerializable($property->getName(), $value);
            $data[$property->getName()] = $value;
        }

        return [
            '__class' => $job::class,
            '__data' => $data,
        ];
    }

    /**
     * Deserialize an array back into a job DTO.
     *
     * @param array<string, mixed> $payload
     */
    public static function deserialize(array $payload): object
    {
        if (!array_key_exists('__class', $payload) || !array_key_exists('__data', $payload)) {
            throw new InvalidArgumentException('Invalid job payload: missing __class or __data.');
        }

        $className = (string) $payload['__class'];

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Job class '{$className}' does not exist.");
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        /** @var array<string, mixed> $payloadData */
        $payloadData = $payload['__data'];
        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $payloadData)) {
                $args[] = self::castValue($param, $payloadData[$name]);

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            throw new InvalidArgumentException("Missing required parameter '{$name}' for job class '{$className}'.");
        }

        /** @var object */
        return $reflection->newInstanceArgs($args);
    }

    /**
     * Validate that a value is JSON-serializable.
     */
    private static function validateSerializable(string $name, mixed $value): void
    {
        if (is_scalar($value) || is_null($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                self::validateSerializable("{$name}[{$k}]", $v);
            }

            return;
        }

        throw new InvalidArgumentException(
            "Job property '{$name}' is not serializable. Only scalar types and arrays are supported.",
        );
    }

    /**
     * Cast a deserialized value to the expected parameter type.
     */
    private static function castValue(\ReflectionParameter $param, mixed $value): mixed
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            return $value;
        }

        if (!is_scalar($value) && $value !== null) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }
}
