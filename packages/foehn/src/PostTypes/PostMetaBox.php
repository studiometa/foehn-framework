<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PostTypes;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\SettingField;
use WP_Post;

/**
 * The "Fields" meta box of one post type: a native field for each #[AsPostMeta]
 * key that declares a `control`.
 *
 * The fields are the ones a settings page draws (SettingField), in the same
 * `form-table`, so the two forms look and behave the same. WordPress shows a meta
 * box in the classic editor and below the content in the block editor, and posts
 * it with the post form in both.
 *
 * Saving changes only the keys present in the request. A REST client, an import
 * or `wp post meta update` saves a post without this form, and must not clear a
 * value it never sent.
 */
final readonly class PostMetaBox
{
    /** The id of the meta box, and the action its nonce is made for. */
    public const string ID = 'foehn-post-meta';

    /** The request field that holds the nonce. */
    public const string NONCE = 'foehn_post_meta_nonce';

    /** The request field that holds the values, as key => value. */
    public const string FIELD = 'foehn_meta';

    /**
     * @param list<AsPostMeta> $fields The keys with a control, in declaration order
     */
    public function __construct(
        public string $postType,
        public array $fields,
    ) {}

    /**
     * Add the meta box to the edit screen, when the user can edit one of its fields.
     */
    public function add(): void
    {
        if ($this->editable() === []) {
            return;
        }

        add_meta_box(self::ID, 'Fields', $this->render(...), $this->postType);
    }

    /**
     * Print the nonce and a row for each field the user can edit.
     */
    public function render(WP_Post $post): void
    {
        wp_nonce_field(self::ID, self::NONCE);

        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($this->editable() as $field) {
            echo self::row($field, get_post_meta($post->ID, $field->key, true));
        }

        echo '</tbody></table>';
    }

    /**
     * Save the posted fields of a post.
     *
     * Each key needs the nonce, the right to edit the post and the key's own
     * capability. A key absent from the request keeps its value. An empty value
     * deletes the key, so the registered default applies again. A key with a
     * `sanitize` method gets the posted value as it is: `update_post_meta()` runs
     * the method, as for every other write. Any other key goes through the
     * sanitiser of its control, as a setting does.
     *
     * @param array<array-key, mixed> $request The posted form, as `$_POST`: slashed
     */
    public function save(int $postId, array $request): void
    {
        $nonce = $request[self::NONCE] ?? null;

        if (!is_string($nonce) || wp_verify_nonce(wp_unslash($nonce), self::ID) === false) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $posted = $request[self::FIELD] ?? null;

        if (!is_array($posted)) {
            return;
        }

        foreach ($this->editable() as $field) {
            if (!array_key_exists($field->key, $posted)) {
                continue;
            }

            $value = wp_unslash($posted[$field->key]);

            if (!is_scalar($value)) {
                continue;
            }

            if ($value === '' && $field->control !== 'checkbox') {
                delete_post_meta($postId, $field->key);

                continue;
            }

            if ($field->sanitize === null) {
                $value = call_user_func(self::setting($field)->sanitizer(), $value);
            }

            // update_post_meta() unslashes its value, as WordPress expects slashed data there.
            update_post_meta($postId, $field->key, wp_slash($value));
        }
    }

    /**
     * The field of a key as a Setting, which SettingField draws and sanitises.
     */
    public static function setting(AsPostMeta $field): Setting
    {
        return match ($field->control) {
            'checkbox' => Setting::bool(label: $field->label, description: $field->description),
            'number' => $field->type === 'integer'
                ? Setting::int(label: $field->label, description: $field->description)
                : Setting::number(label: $field->label, description: $field->description),
            'select' => $field->type === 'integer'
                ? Setting::int(label: $field->label, description: $field->description, options: $field->options)
                : Setting::string(label: $field->label, description: $field->description, options: $field->options),
            default => Setting::string(
                label: $field->label,
                description: $field->description,
                control: (string) $field->control,
            ),
        };
    }

    /**
     * One `form-table` row: the label in the header cell, the field beside it.
     *
     * A checkbox posts nothing when it is not checked, so a hidden empty value
     * comes first: without it, unchecking the box would leave the key unchanged.
     */
    public static function row(AsPostMeta $field, mixed $value): string
    {
        $name = sprintf('%s[%s]', self::FIELD, $field->key);
        $setting = self::setting($field);
        $label = esc_html($setting->label($field->key));

        if ($field->control === 'checkbox') {
            return sprintf(
                '<tr><th scope="row">%s</th><td><input type="hidden" name="%s" value="0" />%s</td></tr>',
                $label,
                esc_attr($name),
                SettingField::render($name, $setting, $value),
            );
        }

        return sprintf(
            '<tr><th scope="row"><label for="%s">%s</label></th><td>%s</td></tr>',
            esc_attr($name),
            $label,
            SettingField::render($name, $setting, $value),
        );
    }

    /**
     * The fields the current user has the capability to write.
     *
     * @return list<AsPostMeta>
     */
    private function editable(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn(AsPostMeta $field): bool => current_user_can($field->capability),
        ));
    }
}
