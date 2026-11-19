<?php

declare(strict_types=1);

use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;

describe('Project', function () {
    it('extends Foehn Post model', function () {
        expect(is_subclass_of(Project::class, Post::class))->toBeTrue();
    });

    it('implements ConfiguresPostType', function () {
        expect(is_subclass_of(Project::class, ConfiguresPostType::class))->toBeTrue();
    });

    it('has AsPostType attribute with correct config', function () {
        $ref = new ReflectionClass(Project::class);
        $attrs = $ref->getAttributes(AsPostType::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('project');
        expect($attr->singular)->toBe('Project');
        expect($attr->plural)->toBe('Projects');
        expect($attr->public)->toBeTrue();
        expect($attr->hasArchive)->toBeTrue();
        expect($attr->showInRest)->toBeTrue();
        expect($attr->menuIcon)->toBe('dashicons-camera-alt');
        expect($attr->supports)->toContain('title', 'editor', 'thumbnail');
        expect($attr->taxonomies)->toContain('project_category', 'project_tag');
    });

    it('configures rewrite slug and menu position', function () {
        $builder = new PostTypeBuilder('project', 'Project', 'Projects');
        $result = Project::configurePostType($builder);

        $args = $result->build();

        expect($args['rewrite']['slug'])->toBe('projects');
        expect($args['rewrite']['with_front'])->toBeFalse();
        expect($args['menu_position'])->toBe(5);
    });

    it('declares its meta keys, so they are in REST and bindable', function () {
        $keys = array_map(
            static fn(ReflectionAttribute $attribute): string => $attribute->newInstance()->key,
            new ReflectionClass(Project::class)->getAttributes(AsPostMeta::class),
        );

        expect($keys)->toBe([
            'client',
            'year',
            'location',
            'camera',
            'format',
            'film',
            'credits',
            'prints',
            'prints_email',
            'prints_phone',
        ]);
    });

    it('gives its meta box a field of each control', function () {
        $controls = array_map(
            static fn(ReflectionAttribute $attribute): ?string => $attribute->newInstance()->control,
            new ReflectionClass(Project::class)->getAttributes(AsPostMeta::class),
        );

        expect(array_values(array_unique($controls)))->toEqualCanonicalizing(array_keys(AsPostMeta::CONTROLS));
    });

    it('offers the formats in its select', function () {
        $format = array_values(array_filter(
            array_map(
                static fn(ReflectionAttribute $attribute): AsPostMeta => $attribute->newInstance(),
                new ReflectionClass(Project::class)->getAttributes(AsPostMeta::class),
            ),
            static fn(AsPostMeta $meta): bool => $meta->key === 'format',
        ))[0];

        expect($format->options)->toBe(Project::FORMATS);
    });
});
