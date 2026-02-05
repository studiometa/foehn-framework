<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsOptionsPage;

describe('AsOptionsPage', function () {
    it('can be instantiated with required parameters only', function () {
        $optionsPage = new AsOptionsPage(
            pageTitle: 'Theme Settings',
            menuTitle: 'Settings',
            menuSlug: 'theme-settings',
        );

        expect($optionsPage->pageTitle)->toBe('Theme Settings');
        expect($optionsPage->menuTitle)->toBe('Settings');
        expect($optionsPage->menuSlug)->toBe('theme-settings');
        expect($optionsPage->capability)->toBe('edit_posts');
        expect($optionsPage->parentSlug)->toBe('');
        expect($optionsPage->position)->toBe(99);
        expect($optionsPage->iconUrl)->toBe('dashicons-admin-generic');
        expect($optionsPage->redirect)->toBeTrue();
        expect($optionsPage->postId)->toBe('options');
        expect($optionsPage->autoload)->toBeTrue();
        expect($optionsPage->updateButton)->toBe('Update');
        expect($optionsPage->updatedMessage)->toBe('Options Updated');
    });

    it('can be instantiated with parent slug for submenu', function () {
        $optionsPage = new AsOptionsPage(
            pageTitle: 'Footer Settings',
            menuTitle: 'Footer',
            menuSlug: 'footer-settings',
            parentSlug: 'theme-settings',
        );

        expect($optionsPage->parentSlug)->toBe('theme-settings');
    });

    it('can be instantiated with all parameters', function () {
        $optionsPage = new AsOptionsPage(
            pageTitle: 'Theme Options',
            menuTitle: 'Options',
            menuSlug: 'theme-options',
            capability: 'manage_options',
            parentSlug: '',
            position: 50,
            iconUrl: 'dashicons-admin-settings',
            redirect: false,
            postId: 'theme_options',
            autoload: false,
            updateButton: 'Save Changes',
            updatedMessage: 'Settings saved successfully',
        );

        expect($optionsPage->pageTitle)->toBe('Theme Options');
        expect($optionsPage->menuTitle)->toBe('Options');
        expect($optionsPage->menuSlug)->toBe('theme-options');
        expect($optionsPage->capability)->toBe('manage_options');
        expect($optionsPage->parentSlug)->toBe('');
        expect($optionsPage->position)->toBe(50);
        expect($optionsPage->iconUrl)->toBe('dashicons-admin-settings');
        expect($optionsPage->redirect)->toBeFalse();
        expect($optionsPage->postId)->toBe('theme_options');
        expect($optionsPage->autoload)->toBeFalse();
        expect($optionsPage->updateButton)->toBe('Save Changes');
        expect($optionsPage->updatedMessage)->toBe('Settings saved successfully');
    });

    it('is readonly', function () {
        expect(AsOptionsPage::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsOptionsPage::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('targets classes only', function () {
        $reflection = new ReflectionClass(AsOptionsPage::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
