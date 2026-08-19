<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsPostMeta] attributes and registers the keys
 * with WordPress.
 *
 * `register_meta()` belongs on `init`, so this is a Main phase discovery.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class PostMetaDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover meta declarations on a class.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        foreach ($class->getReflection()->getAttributes(AsPostMeta::class) as $reflected) {
            $attribute = $reflected->newInstance();

            $this->validate($class, $attribute);

            $this->addItem($location, [
                'attribute' => $attribute,
                'className' => $class->getName(),
                // Resolved here rather than in apply(): the inference reads other
                // attributes off the class, and apply() runs from the cache, where
                // the class has not been reflected.
                'objectSubtype' => $this->resolveObjectSubtype($class, $attribute),
            ]);
        }
    }

    /**
     * Apply discovered meta keys by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerMeta($item);
        }
    }

    /**
     * Register a single meta key with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerMeta(array $item): void
    {
        /** @var AsPostMeta $attribute */
        $attribute = $item['attribute'];
        /** @var class-string $className */
        $className = $item['className'];
        $capability = $attribute->capability;

        $args = [
            'type' => $attribute->type,
            'single' => $attribute->single,
            'description' => $attribute->description,
            'show_in_rest' => $this->restArgument($attribute),
            'object_subtype' => $item['objectSubtype'],
            // Built here, never stored: a closure cannot survive the cache.
            'auth_callback' => static fn(): bool => current_user_can($capability),
        ];

        if ($attribute->default !== null) {
            $args['default'] = $attribute->default;
        }

        if ($attribute->sanitize !== null) {
            $args['sanitize_callback'] = [$className, $attribute->sanitize];
        }

        register_meta($attribute->objectType, $attribute->key, $args);
    }

    /**
     * What `show_in_rest` is given.
     *
     * `true` is enough for a scalar: WordPress derives the schema from `type` and
     * wraps it in an array itself when the key is not single. An array or object
     * has contents WordPress cannot guess, so the declaration carries them.
     *
     * @return bool|array{schema: array<string, mixed>}
     */
    private function restArgument(AsPostMeta $attribute): bool|array
    {
        if (!$attribute->showInRest) {
            return false;
        }

        return $attribute->schema === [] ? true : ['schema' => $attribute->schema];
    }

    /**
     * The post type or taxonomy a key belongs to.
     *
     * `register_meta('post', 'price', […])` without a subtype registers the key
     * for every post type, which is rarely what a model declaring it on itself
     * means. Reading the subtype off the class's own #[AsPostType] is the
     * difference between a correct default and a footgun.
     */
    private function resolveObjectSubtype(ClassReflector $class, AsPostMeta $attribute): string
    {
        if ($attribute->objectSubtype !== null) {
            return $attribute->objectSubtype;
        }

        $subtype = match ($attribute->objectType) {
            'post' => $class->getAttribute(AsPostType::class)?->name,
            'term' => $class->getAttribute(AsTaxonomy::class)?->name,
            // Users and comments have no subtypes in WordPress.
            default => null,
        };

        // A model may map onto a type it does not itself declare — a class for
        // core's `post`, or one whose post type is registered elsewhere.
        $subtype ??= $class->getAttribute(AsTimberModel::class)?->name;

        return $subtype ?? '';
    }

    /**
     * Reject a declaration WordPress would refuse or silently ignore.
     *
     * All of these are mistakes in the theme's own source, so they are raised
     * where they are written rather than left to produce a key that does not
     * appear in the REST API for reasons nothing explains.
     */
    private function validate(ClassReflector $class, AsPostMeta $attribute): void
    {
        $subject = sprintf('#[AsPostMeta(key: \'%s\')] on %s', $attribute->key, $class->getName());

        if (!in_array($attribute->type, AsPostMeta::TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s declares type \'%s\'. register_meta() accepts %s.',
                $subject,
                $attribute->type,
                implode(', ', AsPostMeta::TYPES),
            ));
        }

        if (!in_array($attribute->objectType, AsPostMeta::OBJECT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s declares objectType \'%s\'. register_meta() accepts %s.',
                $subject,
                $attribute->objectType,
                implode(', ', AsPostMeta::OBJECT_TYPES),
            ));
        }

        if (
            $attribute->showInRest
            && $attribute->schema === []
            && in_array($attribute->type, ['array', 'object'], true)
        ) {
            throw new InvalidArgumentException(sprintf(
                '%s is type \'%s\' and shown in REST, so it needs a schema. WordPress cannot describe the contents of an array or an object on its own.',
                $subject,
                $attribute->type,
            ));
        }

        if ($attribute->sanitize !== null) {
            $this->validateSanitizer($class, $attribute, $subject);
        }
    }

    /**
     * A sanitiser has to be a public static method on the declaring class.
     *
     * Static because the declaring class is usually a Timber model: `Timber\Post`
     * declares a protected constructor and is built through its own factories, so
     * there is nothing to call an instance method on when `apply()` runs.
     */
    private function validateSanitizer(ClassReflector $class, AsPostMeta $attribute, string $subject): void
    {
        $reflection = $class->getReflection();
        $name = (string) $attribute->sanitize;

        if (!$reflection->hasMethod($name)) {
            throw new InvalidArgumentException(sprintf(
                '%s names a sanitize method %s() that does not exist.',
                $subject,
                $name,
            ));
        }

        $method = $reflection->getMethod($name);

        if (!$method->isPublic() || !$method->isStatic()) {
            throw new InvalidArgumentException(sprintf(
                '%s names %s() as its sanitizer, which must be public static — the declaring class is not instantiated.',
                $subject,
                $name,
            ));
        }
    }
}
