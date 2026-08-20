<?php

declare(strict_types=1);

use Demo\Models\Project;
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
});
