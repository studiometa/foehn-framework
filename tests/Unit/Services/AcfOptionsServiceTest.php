<?php

declare(strict_types=1);

use Studiometa\Foehn\Services\AcfOptionsService;

beforeEach(function () {
    wp_stub_reset();
    $this->service = new AcfOptionsService();
});

describe('AcfOptionsService', function () {
    it('gets a field value from options page', function () {
        $GLOBALS['wp_stub_acf_fields']['theme-settings']['site_name'] = 'My Site';

        $value = $this->service->get('site_name', 'theme-settings');

        expect($value)->toBe('My Site');
    });

    it('returns null when field does not exist', function () {
        $value = $this->service->get('nonexistent', 'theme-settings');

        expect($value)->toBeNull();
    });

    it('uses default options post_id', function () {
        $GLOBALS['wp_stub_acf_fields']['options']['global_setting'] = 'value';

        $value = $this->service->get('global_setting');

        expect($value)->toBe('value');
    });

    it('gets all fields from options page', function () {
        $GLOBALS['wp_stub_acf_fields']['theme-settings'] = [
            'site_name' => 'My Site',
            'footer_text' => 'Copyright 2024',
        ];

        $values = $this->service->all('theme-settings');

        expect($values)->toBe([
            'site_name' => 'My Site',
            'footer_text' => 'Copyright 2024',
        ]);
    });

    it('returns empty array when no fields exist', function () {
        $values = $this->service->all('empty-page');

        expect($values)->toBe([]);
    });

    it('checks if field has value', function () {
        $GLOBALS['wp_stub_acf_fields']['theme-settings']['site_name'] = 'My Site';
        $GLOBALS['wp_stub_acf_fields']['theme-settings']['empty_field'] = '';
        $GLOBALS['wp_stub_acf_fields']['theme-settings']['false_field'] = false;

        expect($this->service->has('site_name', 'theme-settings'))->toBeTrue();
        expect($this->service->has('empty_field', 'theme-settings'))->toBeFalse();
        expect($this->service->has('false_field', 'theme-settings'))->toBeFalse();
        expect($this->service->has('nonexistent', 'theme-settings'))->toBeFalse();
    });

    it('gets field object', function () {
        $GLOBALS['wp_stub_acf_field_objects']['theme-settings']['site_name'] = [
            'key' => 'field_site_name',
            'name' => 'site_name',
            'type' => 'text',
            'value' => 'My Site',
        ];

        $object = $this->service->getObject('site_name', 'theme-settings');

        expect($object)->toBe([
            'key' => 'field_site_name',
            'name' => 'site_name',
            'type' => 'text',
            'value' => 'My Site',
        ]);
    });

    it('returns null when field object does not exist', function () {
        $object = $this->service->getObject('nonexistent', 'theme-settings');

        expect($object)->toBeNull();
    });
});
