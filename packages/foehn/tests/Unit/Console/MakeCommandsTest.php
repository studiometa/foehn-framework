<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeContextProviderCommand;
use Studiometa\Foehn\Console\Commands\MakeControllerCommand;
use Studiometa\Foehn\Console\Commands\MakeFieldGroupCommand;
use Studiometa\Foehn\Console\Commands\MakeHooksCommand;
use Studiometa\Foehn\Console\Commands\MakeImageSizeCommand;
use Studiometa\Foehn\Console\Commands\MakeMenuCommand;
use Studiometa\Foehn\Console\Commands\MakeOptionsPageCommand;
use Studiometa\Foehn\Console\Commands\MakePostTypeCommand;
use Studiometa\Foehn\Console\Commands\MakeShortcodeCommand;
use Studiometa\Foehn\Console\Commands\MakeTaxonomyCommand;
use Studiometa\Foehn\Console\WpCli;

beforeEach(function () {
    wp_stub_reset();

    $this->root = sys_get_temp_dir() . '/foehn-make-' . bin2hex(random_bytes(6));
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

describe('make:post-type', function () {
    it('generates a post type carrying the requested configuration', function () {
        (new MakePostTypeCommand($this->cli, $this->generator))(['product'], ['class' => 'MakeProductPost']);

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('PostTypes', 'MakeProductPost'), AsPostType::class);

        expect($attribute->name)
            ->toBe('product')
            ->and($attribute->singular)
            ->toBe('Product')
            ->and($attribute->plural)
            ->toBe('Products');
    });

    it('honours explicit singular and plural', function () {
        (new MakePostTypeCommand($this->cli, $this->generator))(['person'], [
            'class' => 'MakePersonPost',
            'singular' => 'Person',
            'plural' => 'People',
        ]);

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('PostTypes', 'MakePersonPost'), AsPostType::class);

        expect($attribute->singular)->toBe('Person')->and($attribute->plural)->toBe('People');
    });

    it('reports the missing name instead of generating', function () {
        (new MakePostTypeCommand($this->cli, $this->generator))([], []);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(glob($this->appPath . '/PostTypes/*.php'))->toBeEmpty();
    });

    it('writes nothing on a dry run', function () {
        (new MakePostTypeCommand($this->cli, $this->generator))(['product'], ['dry-run' => true]);

        expect(glob($this->appPath . '/PostTypes/*.php'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_cli_log'))->not->toBeEmpty();
    });

    it('refuses to overwrite without --force', function () {
        $command = new MakePostTypeCommand($this->cli, $this->generator);
        $command(['product'], ['class' => 'GuardedPost']);

        $path = ($this->path)('PostTypes', 'GuardedPost');
        file_put_contents($path, '<?php // hand-edited');

        $command(['product'], ['class' => 'GuardedPost']);

        expect(file_get_contents($path))->toBe('<?php // hand-edited');
        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('overwrites with --force', function () {
        $command = new MakePostTypeCommand($this->cli, $this->generator);
        $command(['product'], ['class' => 'ForcedPost']);

        $path = ($this->path)('PostTypes', 'ForcedPost');
        file_put_contents($path, '<?php // hand-edited');

        $command(['product'], ['class' => 'ForcedPost', 'force' => true]);

        expect(file_get_contents($path))->toContain('#[AsPostType(');
    });
});

describe('make:taxonomy', function () {
    it('generates a taxonomy with post types and hierarchy', function () {
        (new MakeTaxonomyCommand($this->cli, $this->generator))(['genre'], [
            'class' => 'MakeGenreTerm',
            'post-types' => 'book,film',
            'hierarchical' => true,
        ]);

        /** @var AsTaxonomy $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Taxonomies', 'MakeGenreTerm'), AsTaxonomy::class);

        expect($attribute->name)
            ->toBe('genre')
            ->and($attribute->postTypes)
            ->toBe(['book', 'film'])
            ->and($attribute->hierarchical)
            ->toBeTrue();
    });

    it('defaults to the post type and a flat taxonomy', function () {
        (new MakeTaxonomyCommand($this->cli, $this->generator))(['mood'], ['class' => 'MakeMoodTerm']);

        /** @var AsTaxonomy $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Taxonomies', 'MakeMoodTerm'), AsTaxonomy::class);

        expect($attribute->postTypes)->toBe(['post'])->and($attribute->hierarchical)->toBeFalse();
    });
});

describe('make:block', function () {
    it('generates a block with the namespaced name and category', function () {
        (new MakeBlockCommand($this->cli, $this->generator))(['hero'], [
            'class' => 'MakeHeroBlock',
            'namespace' => 'my-theme',
            'category' => 'layout',
        ]);

        /** @var AsBlock $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Blocks', 'MakeHeroBlock'), AsBlock::class);

        expect($attribute->name)
            ->toBe('my-theme/hero')
            ->and($attribute->title)
            ->toBe('Hero')
            ->and($attribute->category)
            ->toBe('layout')
            ->and($attribute->interactivity)
            ->toBeFalse();
    });

    it('generates an interactive block from the interactive stub', function () {
        (new MakeBlockCommand($this->cli, $this->generator))(['counter'], [
            'class' => 'MakeCounterBlock',
            'interactive' => true,
        ]);

        $contents = file_get_contents(($this->path)('Blocks', 'MakeCounterBlock'));

        /** @var AsBlock $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Blocks', 'MakeCounterBlock'), AsBlock::class);

        expect($attribute->name)->toBe('theme/counter')->and($attribute->interactivity)->toBeTrue();
        expect($contents)->toContain('InteractiveBlockInterface');
    });
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

describe('make:controller', function () {
    it('keeps a single template a string and renders it', function () {
        (new MakeControllerCommand($this->cli, $this->generator))(['MakeSingleProduct'], [
            'templates' => 'single-product',
        ]);

        $path = ($this->path)('Controllers', 'MakeSingleProductController');

        /** @var AsTemplateController $attribute */
        $attribute = ($this->generatedAttribute)($path, AsTemplateController::class);

        expect($attribute->templates)->toBe('single-product');
        expect(file_get_contents($path))->toContain("render('single-product'");
    });

    it('keeps several templates an array and renders the first', function () {
        (new MakeControllerCommand($this->cli, $this->generator))(['MakeArchives'], [
            'templates' => 'archive-product,archive-service',
        ]);

        $path = ($this->path)('Controllers', 'MakeArchivesController');

        /** @var AsTemplateController $attribute */
        $attribute = ($this->generatedAttribute)($path, AsTemplateController::class);

        expect($attribute->templates)->toBe(['archive-product', 'archive-service']);
        expect(file_get_contents($path))->toContain("render('archive-product'");
    });
});

describe('make:context-provider', function () {
    it('generates a provider for the requested templates', function () {
        (new MakeContextProviderCommand($this->cli, $this->generator))(['MakeGlobal'], [
            'templates' => 'single,archive-*',
        ]);

        /** @var AsContextProvider $attribute */
        $attribute = ($this->generatedAttribute)(
            ($this->path)('ContextProviders', 'MakeGlobalContextProvider'),
            AsContextProvider::class,
        );

        expect($attribute->getTemplates())->toBe(['single', 'archive-*']);
    });
});

describe('make:image-size', function () {
    it('generates an image size with dimensions and crop', function () {
        (new MakeImageSizeCommand($this->cli, $this->generator))(['MakeHeroWide'], [
            'width' => '1600',
            'height' => '900',
            'crop' => true,
        ]);

        $path = ($this->path)('ImageSizes', 'MakeHeroWide');

        /** @var AsImageSize $attribute */
        $attribute = ($this->generatedAttribute)($path, AsImageSize::class);

        expect($attribute->width)->toBe(1600);
        expect($attribute->height)->toBe(900);
        expect($attribute->crop)->toBeTrue();
        expect(file_get_contents($path))->not->toContain('DummyImageSize');
    });
});

describe('make:menu and make:shortcode and make:hooks', function () {
    it('generates a menu whose helper reads the new location', function () {
        (new MakeMenuCommand($this->cli, $this->generator))(['FooterMenu'], ['location' => 'footer']);

        $contents = file_get_contents(($this->path)('Menus', 'FooterMenu'));

        expect($contents)->toContain("location: 'footer'");
        expect($contents)->toContain("Timber::get_menu('footer'");
        expect($contents)->not->toContain('DummyMenu');
    });

    it('generates a shortcode with the tag everywhere the stub named it', function () {
        (new MakeShortcodeCommand($this->cli, $this->generator))(['gallery'], ['class' => 'MakeGalleryShortcode']);

        $contents = file_get_contents(($this->path)('Shortcodes', 'MakeGalleryShortcode'));

        expect($contents)->toContain("#[AsShortcode(tag: 'gallery')]");
        expect($contents)->not->toContain('dummy-shortcode');
    });

    it('generates a hooks class named after the request', function () {
        (new MakeHooksCommand($this->cli, $this->generator))(['Theme'], []);

        $contents = file_get_contents(($this->path)('Hooks', 'ThemeHooks'));

        expect($contents)->toContain('class ThemeHooks');
        expect($contents)->not->toContain('DummyHooks');
    });
});

describe('generated files', function () {
    it('are valid PHP for every make command', function () {
        (new MakePostTypeCommand($this->cli, $this->generator))(['lint-product'], ['class' => 'LintPost']);
        (new MakeTaxonomyCommand($this->cli, $this->generator))(['lint-genre'], ['class' => 'LintTerm']);
        (new MakeBlockCommand($this->cli, $this->generator))(['lint-hero'], ['class' => 'LintBlock']);
        (new MakeBlockCommand($this->cli, $this->generator))(['lint-counter'], [
            'class' => 'LintInteractiveBlock',
            'interactive' => true,
        ]);
        (new MakeAcfBlockCommand($this->cli, $this->generator))(['lint-acf'], [
            'class' => 'LintAcfBlock',
            'fields' => 'text,image,repeater',
        ]);
        (new MakeFieldGroupCommand($this->cli, $this->generator))(['LintFields'], ['post-type' => 'page']);
        (new MakeOptionsPageCommand($this->cli, $this->generator))(['lint-options'], []);
        (new MakeControllerCommand($this->cli, $this->generator))(['LintController'], ['templates' => 'single,page']);
        (new MakeContextProviderCommand($this->cli, $this->generator))(['LintProvider'], ['templates' => 'single']);
        (new MakeImageSizeCommand($this->cli, $this->generator))(['LintImageSize'], []);
        (new MakeMenuCommand($this->cli, $this->generator))(['LintMenu'], []);
        (new MakeShortcodeCommand($this->cli, $this->generator))(['lint-tag'], ['class' => 'LintShortcode']);
        (new MakeHooksCommand($this->cli, $this->generator))(['Lint'], []);

        $generated = [];
        $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->appPath));

        /** @var SplFileInfo $entry */
        foreach ($directory as $entry) {
            if ($entry->getExtension() !== 'php') {
                continue;
            }

            $generated[] = $entry->getPathname();
        }

        expect($generated)->toHaveCount(13);

        foreach ($generated as $path) {
            exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

            expect($status)->toBe(0, $path . "\n" . implode("\n", $output));
        }
    });

    it('leave no dummy placeholder behind', function () {
        (new MakePostTypeCommand($this->cli, $this->generator))(['clean-product'], ['class' => 'CleanPost']);
        (new MakeTaxonomyCommand($this->cli, $this->generator))(['clean-genre'], ['class' => 'CleanTerm']);
        (new MakeBlockCommand($this->cli, $this->generator))(['clean-hero'], ['class' => 'CleanBlock']);
        (new MakeMenuCommand($this->cli, $this->generator))(['CleanMenu'], []);
        (new MakeShortcodeCommand($this->cli, $this->generator))(['clean-tag'], ['class' => 'CleanShortcode']);
        (new MakeHooksCommand($this->cli, $this->generator))(['Clean'], []);

        $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->appPath));

        /** @var SplFileInfo $entry */
        foreach ($directory as $entry) {
            if ($entry->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($entry->getPathname());

            expect($contents)->not->toContain('dummy')->and($contents)->not->toContain('Dummy');
        }
    });
});
