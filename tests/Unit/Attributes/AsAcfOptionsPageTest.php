<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;

describe('AsAcfOptionsPage', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsAcfOptionsPage(pageTitle: 'Site Settings');

        expect($attribute->pageTitle)->toBe('Site Settings');
        expect($attribute->menuTitle)->toBeNull();
        expect($attribute->menuSlug)->toBeNull();
        expect($attribute->capability)->toBe('edit_posts');
        expect($attribute->position)->toBeNull();
        expect($attribute->parentSlug)->toBeNull();
        expect($attribute->iconUrl)->toBeNull();
        expect($attribute->redirect)->toBeTrue();
        expect($attribute->postId)->toBeNull();
        expect($attribute->autoload)->toBeTrue();
        expect($attribute->updateButton)->toBeNull();
        expect($attribute->updatedMessage)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsAcfOptionsPage(
            pageTitle: 'Theme Options',
            menuTitle: 'Theme',
            menuSlug: 'theme-options',
            capability: 'manage_options',
            position: 59,
            parentSlug: null,
            iconUrl: 'dashicons-admin-generic',
            redirect: false,
            postId: 'theme_options',
            autoload: false,
            updateButton: 'Save Settings',
            updatedMessage: 'Settings saved!',
        );

        expect($attribute->pageTitle)->toBe('Theme Options');
        expect($attribute->menuTitle)->toBe('Theme');
        expect($attribute->menuSlug)->toBe('theme-options');
        expect($attribute->capability)->toBe('manage_options');
        expect($attribute->position)->toBe(59);
        expect($attribute->parentSlug)->toBeNull();
        expect($attribute->iconUrl)->toBe('dashicons-admin-generic');
        expect($attribute->redirect)->toBeFalse();
        expect($attribute->postId)->toBe('theme_options');
        expect($attribute->autoload)->toBeFalse();
        expect($attribute->updateButton)->toBe('Save Settings');
        expect($attribute->updatedMessage)->toBe('Settings saved!');
    });

    it('returns effective menu slug from pageTitle when not set', function () {
        $attribute = new AsAcfOptionsPage(pageTitle: 'Site Settings');

        expect($attribute->getMenuSlug())->toBe('site-settings');
    });

    it('returns explicit menu slug when set', function () {
        $attribute = new AsAcfOptionsPage(
            pageTitle: 'Site Settings',
            menuSlug: 'custom-slug',
        );

        expect($attribute->getMenuSlug())->toBe('custom-slug');
    });

    it('returns effective menu title from pageTitle when not set', function () {
        $attribute = new AsAcfOptionsPage(pageTitle: 'Site Settings');

        expect($attribute->getMenuTitle())->toBe('Site Settings');
    });

    it('returns explicit menu title when set', function () {
        $attribute = new AsAcfOptionsPage(
            pageTitle: 'Site Settings',
            menuTitle: 'Settings',
        );

        expect($attribute->getMenuTitle())->toBe('Settings');
    });

    it('returns effective post_id from menu slug when not set', function () {
        $attribute = new AsAcfOptionsPage(
            pageTitle: 'Site Settings',
            menuSlug: 'my-settings',
        );

        expect($attribute->getPostId())->toBe('my-settings');
    });

    it('returns explicit post_id when set', function () {
        $attribute = new AsAcfOptionsPage(
            pageTitle: 'Site Settings',
            postId: 'custom_post_id',
        );

        expect($attribute->getPostId())->toBe('custom_post_id');
    });

    it('detects sub-page when parentSlug is set', function () {
        $attribute = new AsAcfOptionsPage(
            pageTitle: 'Sub Page',
            parentSlug: 'parent-page',
        );

        expect($attribute->isSubPage())->toBeTrue();
    });

    it('detects top-level page when parentSlug is not set', function () {
        $attribute = new AsAcfOptionsPage(pageTitle: 'Top Level');

        expect($attribute->isSubPage())->toBeFalse();
    });

    it('is readonly', function () {
        expect(AsAcfOptionsPage::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsAcfOptionsPage::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
