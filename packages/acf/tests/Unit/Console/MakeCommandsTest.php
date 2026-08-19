<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeFieldGroupCommand;
use Studiometa\Foehn\Console\Commands\MakeOptionsPageCommand;
use Studiometa\Foehn\Console\WpCli;

beforeEach(function () {
    wp_stub_reset();

    $this->root = sys_get_temp_dir() . '/foehn-acf-make-' . bin2hex(random_bytes(6));
    $this->appPath = $this->root . '/app';

    mkdir($this->appPath, 0o755, true);
    file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Theme\\' => 'app/']]]));

    $this->cli = new WpCli();
    $this->generator = new ClassFileGenerator($this->appPath);

    /**
     * Read the attribute of a class the command just generated.
     *
     * Each generated class gets a unique name, so requiring it cannot collide
     * with another test's class in the same process.
     */
    $this->generatedAttribute = function (string $path, string $attributeClass): object {
        expect($path)->toBeFile();

        require $path;

        $fqcn =
            'Theme\\'
            . str_replace('/', '\\', trim(substr(dirname($path), strlen($this->appPath)), '/'))
            . '\\'
            . basename($path, '.php');

        $attributes = new ReflectionClass($fqcn)->getAttributes($attributeClass);

        expect($attributes)->toHaveCount(1);

        return $attributes[0]->newInstance();
    };

    $this->path = fn(string $subdirectory, string $class): string => "{$this->appPath}/{$subdirectory}/{$class}.php";
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->root));
});

describe('make:acf-block', function () {
    it('generates an ACF block with the requested mode and category', function () {
        (new MakeAcfBlockCommand($this->cli, $this->generator))(['testimonial'], [
            'class' => 'MakeTestimonialBlock',
            'category' => 'formatting',
            'mode' => 'edit',
        ]);

        /** @var AsAcfBlock $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Blocks', 'MakeTestimonialBlock'), AsAcfBlock::class);

        expect($attribute->name)
            ->toBe('testimonial')
            ->and($attribute->category)
            ->toBe('formatting')
            ->and($attribute->mode)
            ->toBe('edit');
    });

    it('seeds the field builder with the block key', function () {
        (new MakeAcfBlockCommand($this->cli, $this->generator))(['call-to-action'], ['class' => 'MakeCtaBlock']);

        $contents = file_get_contents(($this->path)('Blocks', 'MakeCtaBlock'));

        expect($contents)->toContain("new FieldsBuilder('call_to_action')");
        expect($contents)->not->toContain('dummy_acf_block');
    });

    it('generates the requested fields', function () {
        (new MakeAcfBlockCommand($this->cli, $this->generator))(['gallery'], [
            'class' => 'MakeGalleryBlock',
            'fields' => 'text,image',
        ]);

        $contents = file_get_contents(($this->path)('Blocks', 'MakeGalleryBlock'));

        expect($contents)->toContain('addText(');
        expect($contents)->toContain('addImage(');
    });

    it('rejects an invalid mode without generating', function () {
        (new MakeAcfBlockCommand($this->cli, $this->generator))(['x'], ['mode' => 'nonsense']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(glob($this->appPath . '/Blocks/*.php'))->toBeEmpty();
    });

    it('rejects an unknown field type without generating', function () {
        (new MakeAcfBlockCommand($this->cli, $this->generator))(['x'], ['fields' => 'text,nonsense']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(glob($this->appPath . '/Blocks/*.php'))->toBeEmpty();
    });
});

describe('make:field-group', function () {
    it('locates the field group on the requested post type', function () {
        (new MakeFieldGroupCommand($this->cli, $this->generator))(['MakePropertyFields'], ['post-type' => 'property']);

        $path = ($this->path)('Fields/PostType', 'MakePropertyFields');

        /** @var AsAcfFieldGroup $attribute */
        $attribute = ($this->generatedAttribute)($path, AsAcfFieldGroup::class);

        expect($attribute->location)->toBe(['post_type' => 'property']);
    });

    it('locates the field group on the requested taxonomy', function () {
        (new MakeFieldGroupCommand($this->cli, $this->generator))(['MakeGenreFields'], ['taxonomy' => 'genre']);

        /** @var AsAcfFieldGroup $attribute */
        $attribute = ($this->generatedAttribute)(
            ($this->path)('Fields/Taxonomy', 'MakeGenreFields'),
            AsAcfFieldGroup::class,
        );

        expect($attribute->location)->toBe(['taxonomy' => 'genre']);
    });

    it('locates the field group on the requested page template', function () {
        (new MakeFieldGroupCommand($this->cli, $this->generator))(['MakeLandingFields'], [
            'page-template' => 'landing',
        ]);

        /** @var AsAcfFieldGroup $attribute */
        $attribute = ($this->generatedAttribute)(
            ($this->path)('Fields/Page', 'MakeLandingFields'),
            AsAcfFieldGroup::class,
        );

        expect($attribute->location)->toBe(['page_template' => 'landing.php']);
    });

    it('seeds the field builder with the group key', function () {
        (new MakeFieldGroupCommand($this->cli, $this->generator))(['MakeSeoFields'], ['post-type' => 'page']);

        $contents = file_get_contents(($this->path)('Fields/PostType', 'MakeSeoFields'));

        expect($contents)->toContain("new FieldsBuilder('make_seo_fields')");
        expect($contents)->not->toContain('dummy_field_group');
    });
});

describe('make:options-page', function () {
    it('generates a top level options page', function () {
        (new MakeOptionsPageCommand($this->cli, $this->generator))(['make-theme-settings'], []);

        /** @var AsAcfOptionsPage $attribute */
        $attribute = ($this->generatedAttribute)(
            ($this->path)('Fields/Options', 'MakeThemeSettings'),
            AsAcfOptionsPage::class,
        );

        expect($attribute->pageTitle)->toBe('Make Theme Settings');
        expect($attribute->menuSlug)->toBe('make-theme-settings');
        expect($attribute->parentSlug)->toBeNull();
    });

    it('generates a sub page under the requested parent', function () {
        (new MakeOptionsPageCommand($this->cli, $this->generator))(['make-footer-settings'], [
            'parent' => 'make-theme-settings',
            'icon' => 'dashicons-menu',
        ]);

        /** @var AsAcfOptionsPage $attribute */
        $attribute = ($this->generatedAttribute)(
            ($this->path)('Fields/Options', 'MakeFooterSettings'),
            AsAcfOptionsPage::class,
        );

        expect($attribute->parentSlug)
            ->toBe('make-theme-settings')
            ->and($attribute->iconUrl)
            ->toBe('dashicons-menu')
            ->and($attribute->isSubPage())
            ->toBeTrue();
    });
});
