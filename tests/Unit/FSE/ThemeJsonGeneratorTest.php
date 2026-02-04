<?php

declare(strict_types=1);

use Studiometa\WPTempest\FSE\ThemeJsonGenerator;

describe('ThemeJsonGenerator', function () {
    it('generates minimal theme.json', function () {
        $generator = new ThemeJsonGenerator();
        $json = $generator->generate();

        expect($json['$schema'])->toBe('https://schemas.wp.org/trunk/theme.json');
        expect($json['version'])->toBe(3);
    });

    it('can set settings', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setSettings([
            'color' => ['custom' => false],
            'typography' => ['customFontSize' => false],
        ]);

        $json = $generator->generate();

        expect($json['settings']['color']['custom'])->toBeFalse();
        expect($json['settings']['typography']['customFontSize'])->toBeFalse();
    });

    it('can merge settings', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setSettings(['color' => ['custom' => false]]);
        $generator->mergeSettings(['typography' => ['fluid' => true]]);

        $json = $generator->generate();

        expect($json['settings']['color']['custom'])->toBeFalse();
        expect($json['settings']['typography']['fluid'])->toBeTrue();
    });

    it('can set color palette', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setColorPalette([
            ['slug' => 'primary', 'name' => 'Primary', 'color' => '#0073aa'],
            ['slug' => 'secondary', 'name' => 'Secondary', 'color' => '#23282d'],
        ]);

        $json = $generator->generate();

        expect($json['settings']['color']['palette'])->toHaveCount(2);
        expect($json['settings']['color']['palette'][0]['slug'])->toBe('primary');
    });

    it('can set font families', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setFontFamilies([
            ['fontFamily' => 'Inter, sans-serif', 'name' => 'Inter', 'slug' => 'inter'],
        ]);

        $json = $generator->generate();

        expect($json['settings']['typography']['fontFamilies'])->toHaveCount(1);
        expect($json['settings']['typography']['fontFamilies'][0]['slug'])->toBe('inter');
    });

    it('can set font sizes', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setFontSizes([
            ['slug' => 'small', 'name' => 'Small', 'size' => '0.875rem'],
            ['slug' => 'medium', 'name' => 'Medium', 'size' => '1rem'],
        ]);

        $json = $generator->generate();

        expect($json['settings']['typography']['fontSizes'])->toHaveCount(2);
    });

    it('can set spacing sizes', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setSpacingSizes([
            ['slug' => '20', 'name' => '1', 'size' => '0.5rem'],
            ['slug' => '30', 'name' => '2', 'size' => '1rem'],
        ]);

        $json = $generator->generate();

        expect($json['settings']['spacing']['spacingSizes'])->toHaveCount(2);
    });

    it('can set styles', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setStyles([
            'color' => ['background' => '#ffffff', 'text' => '#000000'],
        ]);

        $json = $generator->generate();

        expect($json['styles']['color']['background'])->toBe('#ffffff');
    });

    it('can add custom templates', function () {
        $generator = new ThemeJsonGenerator();
        $generator->addCustomTemplate('blank', 'Blank Template', ['page']);
        $generator->addCustomTemplate('full-width', 'Full Width', ['page', 'post']);

        $json = $generator->generate();

        expect($json['customTemplates'])->toHaveCount(2);
        expect($json['customTemplates'][0]['name'])->toBe('blank');
        expect($json['customTemplates'][0]['title'])->toBe('Blank Template');
    });

    it('can add template parts', function () {
        $generator = new ThemeJsonGenerator();
        $generator->addTemplatePart('header', 'Header', 'header');
        $generator->addTemplatePart('footer', 'Footer', 'footer');

        $json = $generator->generate();

        expect($json['templateParts'])->toHaveCount(2);
        expect($json['templateParts'][0]['area'])->toBe('header');
    });

    it('can set patterns', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setPatterns(['theme/hero', 'theme/cta']);

        $json = $generator->generate();

        expect($json['patterns'])->toBe(['theme/hero', 'theme/cta']);
    });

    it('supports fluent interface', function () {
        $generator = new ThemeJsonGenerator();

        $result = $generator->setSettings(['color' => ['custom' => false]])->setColorPalette([])->addCustomTemplate(
            'blank',
            'Blank',
        );

        expect($result)->toBe($generator);
    });

    it('writes to file', function () {
        $generator = new ThemeJsonGenerator();
        $generator->setSettings(['color' => ['custom' => false]]);

        $tempFile = sys_get_temp_dir() . '/theme-' . uniqid() . '.json';

        $result = $generator->write($tempFile);

        expect($result)->toBeTrue();
        expect(file_exists($tempFile))->toBeTrue();

        $content = json_decode(file_get_contents($tempFile), true);
        expect($content['version'])->toBe(3);

        unlink($tempFile);
    });
});
