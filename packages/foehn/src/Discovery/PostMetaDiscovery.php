<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\PostTypes\PostMetaBox;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsPostMeta] attributes and registers the keys
 * with WordPress.
 *
 * `register_meta()` belongs on `init`, so this is a Main phase discovery. The
 * keys with a `control` also get a meta box for their post type, added on
 * `add_meta_boxes_{$postType}` and saved on `save_post_{$postType}`, the hooks
 * that own those APIs.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class PostMetaDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

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

            $problem = $this->problem($class, $attribute);

            if ($problem !== null) {
                $this->problems->report($location, $class->getName(), '#[AsPostMeta]', ...$problem);

                continue;
            }

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
        /** @var array<string, list<AsPostMeta>> $controls */
        $controls = [];

        foreach ($this->getItems() as $item) {
            $this->registerMeta($item);

            /** @var AsPostMeta $attribute */
            $attribute = $item['attribute'];

            if ($attribute->control !== null) {
                $controls[(string) $item['objectSubtype']][] = $attribute;
            }
        }

        foreach ($controls as $postType => $fields) {
            $this->registerMetaBox(new PostMetaBox($postType, $fields));
        }
    }

    /**
     * Add the meta box on its post type's edit screen, and save it with the post.
     *
     * `save_post_{$postType}` never fires for a revision or an autosave of
     * another type, and the nonce is only in a request the edit form sent.
     */
    private function registerMetaBox(PostMetaBox $box): void
    {
        add_action('add_meta_boxes_' . $box->postType, $box->add(...));
        add_action('save_post_' . $box->postType, static function (int $postId) use ($box): void {
            // The nonce is checked in PostMetaBox::save().
            $box->save($postId, $_POST);
        });
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
     * What is wrong with a declaration WordPress would refuse or silently ignore,
     * and the fix, or null when nothing is.
     *
     * All of these are mistakes in the theme's own source. The key is skipped and
     * reported, rather than left to produce a key that does not appear in the REST
     * API for reasons nothing explains.
     *
     * @return array{reason: string, fix: string}|null
     */
    private function problem(ClassReflector $class, AsPostMeta $attribute): ?array
    {
        $subject = sprintf('#[AsPostMeta(key: \'%s\')] on %s', $attribute->key, $class->getName());

        if (!in_array($attribute->type, AsPostMeta::TYPES, true)) {
            return [
                'reason' => sprintf(
                    '%s declares type \'%s\', which register_meta() does not accept, so the key is not registered.',
                    $subject,
                    $attribute->type,
                ),
                'fix' => sprintf('Set type to one of %s.', implode(', ', AsPostMeta::TYPES)),
            ];
        }

        if (!in_array($attribute->objectType, AsPostMeta::OBJECT_TYPES, true)) {
            return [
                'reason' => sprintf(
                    '%s declares objectType \'%s\', which register_meta() does not accept, so the key is not registered.',
                    $subject,
                    $attribute->objectType,
                ),
                'fix' => sprintf('Set objectType to one of %s.', implode(', ', AsPostMeta::OBJECT_TYPES)),
            ];
        }

        if (
            $attribute->showInRest
            && $attribute->schema === []
            && in_array($attribute->type, ['array', 'object'], true)
        ) {
            return [
                'reason' => sprintf(
                    '%s is type \'%s\' and shown in REST, so it needs a schema: WordPress cannot describe the contents of an array or an object on its own.',
                    $subject,
                    $attribute->type,
                ),
                'fix' => 'Add a `schema` to #[AsPostMeta], or set showInRest: false.',
            ];
        }

        if ($attribute->control !== null) {
            $problem = $this->controlProblem($class, $attribute, $subject);

            if ($problem !== null) {
                return $problem;
            }
        }

        if ($attribute->sanitize === null) {
            return null;
        }

        return $this->sanitizerProblem($class, $attribute, $subject);
    }

    /**
     * A control needs a field the meta box can draw for the key's type, and a
     * post type to draw it on: one field edits one value of one post.
     *
     * @return array{reason: string, fix: string}|null
     */
    private function controlProblem(ClassReflector $class, AsPostMeta $attribute, string $subject): ?array
    {
        $control = (string) $attribute->control;
        $types = AsPostMeta::CONTROLS[$control] ?? null;

        if ($types === null) {
            return [
                'reason' => sprintf('%s declares control \'%s\', which the meta box cannot draw.', $subject, $control),
                'fix' => sprintf('Set control to one of %s.', implode(', ', array_keys(AsPostMeta::CONTROLS))),
            ];
        }

        if (!in_array($attribute->type, $types, true)) {
            return [
                'reason' => sprintf(
                    '%s is type \'%s\', which a %s control cannot edit.',
                    $subject,
                    $attribute->type,
                    $control,
                ),
                'fix' => sprintf('Set type to %s, or choose another control.', implode(' or ', $types)),
            ];
        }

        if ($control === 'select' && $attribute->options === []) {
            return [
                'reason' => sprintf('%s is a select with no options.', $subject),
                'fix' => 'Add `options` as value => label.',
            ];
        }

        if (!$attribute->single || $attribute->objectType !== 'post') {
            return [
                'reason' => sprintf(
                    '%s has a control, which edits one value of a post, but the key is not single post meta.',
                    $subject,
                ),
                'fix' => 'Remove `control`, or declare the key with single: true and objectType: \'post\'.',
            ];
        }

        if ($this->resolveObjectSubtype($class, $attribute) === '') {
            return [
                'reason' => sprintf('%s has a control, but no post type to show the meta box on.', $subject),
                'fix' => 'Declare the key on a class with #[AsPostType] or #[AsTimberModel], or set objectSubtype.',
            ];
        }

        return null;
    }

    /**
     * A sanitiser has to be a public static method on the declaring class.
     *
     * Static because the declaring class is usually a Timber model: `Timber\Post`
     * declares a protected constructor and is built through its own factories, so
     * there is nothing to call an instance method on when `apply()` runs.
     *
     * @return array{reason: string, fix: string}|null
     */
    private function sanitizerProblem(ClassReflector $class, AsPostMeta $attribute, string $subject): ?array
    {
        $reflection = $class->getReflection();
        $name = (string) $attribute->sanitize;

        if (!$reflection->hasMethod($name)) {
            return [
                'reason' => sprintf('%s names a sanitize method %s() that does not exist.', $subject, $name),
                'fix' => sprintf(
                    'Add `public static function %s(mixed $value): mixed` to %s, or remove `sanitize`.',
                    $name,
                    $class->getName(),
                ),
            ];
        }

        $method = $reflection->getMethod($name);

        if (!$method->isPublic() || !$method->isStatic()) {
            return [
                'reason' => sprintf(
                    '%s names %s() as its sanitizer, which must be public static: the declaring class is not instantiated.',
                    $subject,
                    $name,
                ),
                'fix' => sprintf('Declare %s::%s() as `public static`.', $class->getName(), $name),
            ];
        }

        return null;
    }
}
