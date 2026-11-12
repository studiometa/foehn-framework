<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\Commands\MakeBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeContextProviderCommand;
use Studiometa\Foehn\Console\Commands\MakeControllerCommand;
use Studiometa\Foehn\Console\Commands\MakeHooksCommand;
use Studiometa\Foehn\Console\Commands\MakeImageSizeCommand;
use Studiometa\Foehn\Console\Commands\MakeMenuCommand;
use Studiometa\Foehn\Console\Commands\MakeModelCommand;
use Studiometa\Foehn\Console\Commands\MakePostTypeCommand;
use Studiometa\Foehn\Console\Commands\MakeShortcodeCommand;
use Studiometa\Foehn\Console\Commands\MakeTaxonomyCommand;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\WpCli;

beforeEach(function () {
    wp_stub_reset();

    $this->root = sys_get_temp_dir() . '/foehn-make-' . bin2hex(random_bytes(6));
    $this->appPath = $this->root . '/app';

    mkdir($this->appPath, 0o755, true);
    file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Theme\\' => 'app/']]]));

    $this->cli = new WpCli();
    $this->generator = new ClassFileGenerator($this->appPath);
    $this->conventions = testProjectConventions($this->appPath);
    $this->scaffolder = new Scaffolder($this->cli, $this->generator, $this->conventions);

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
    tearDownTestContainer();
});

describe('make:post-type', function () {
    it('generates a post type carrying the requested configuration', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['product'], [
            'class' => 'MakeProductPost',
        ]);

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Models', 'MakeProductPost'), AsPostType::class);

        expect($attribute->name)
            ->toBe('product')
            ->and($attribute->singular)
            ->toBe('Product')
            ->and($attribute->plural)
            ->toBe('Products');
    });

    it('honours explicit singular and plural', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['person'], [
            'class' => 'MakePersonPost',
            'singular' => 'Person',
            'plural' => 'People',
        ]);

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Models', 'MakePersonPost'), AsPostType::class);

        expect($attribute->singular)->toBe('Person')->and($attribute->plural)->toBe('People');
    });

    it('reports the missing name instead of generating', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))([], []);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(glob($this->appPath . '/Models/*.php'))->toBeEmpty();
    });

    it('writes nothing on a dry run', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['product'], ['dry-run' => true]);

        expect(glob($this->appPath . '/Models/*.php'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_cli_log'))->not->toBeEmpty();
    });

    it('refuses to overwrite without --force', function () {
        $command = new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions);
        $command(['product'], ['class' => 'GuardedPost']);

        $path = ($this->path)('Models', 'GuardedPost');
        file_put_contents($path, '<?php // hand-edited');

        $command(['product'], ['class' => 'GuardedPost']);

        expect(file_get_contents($path))->toBe('<?php // hand-edited');
        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('overwrites with --force', function () {
        $command = new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions);
        $command(['product'], ['class' => 'ForcedPost']);

        $path = ($this->path)('Models', 'ForcedPost');
        file_put_contents($path, '<?php // hand-edited');

        $command(['product'], ['class' => 'ForcedPost', 'force' => true]);

        expect(file_get_contents($path))->toContain('#[AsPostType(');
    });

    it('names the class after the post type and extends the Føhn post model', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['make-recital'], []);

        $contents = file_get_contents(($this->path)('Models', 'MakeRecital'));

        expect($contents)->not->toContain('custom-fields');
        expect($contents)
            ->toContain('use Studiometa\\Foehn\\Models\\Post;')
            ->toContain('final class MakeRecital extends Post')
            ->not->toContain('\\Timber\\Post');
    });

    it('declares each meta field with an accessor, and the rewrite slug', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['event'], [
            'class' => 'MakeEventPost',
            'rewrite-slug' => 'events',
            'meta' => 'start_date, venue,capacity:integer,price:number,sold_out:boolean',
        ]);

        $path = ($this->path)('Models', 'MakeEventPost');

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)($path, AsPostType::class);
        $class = new ReflectionClass('Theme\\Models\\MakeEventPost');
        $meta = array_map(static fn(ReflectionAttribute $meta): array => [
            $meta->newInstance()->key,
            $meta->newInstance()->type,
        ], $class->getAttributes(AsPostMeta::class));
        $returnType = static fn(string $method): string => (string) $class->getMethod($method)->getReturnType();

        expect($attribute->rewriteSlug)->toBe('events');
        expect($attribute->supports)->toContain('custom-fields');
        expect($meta)->toBe([
            ['start_date', 'string'],
            ['venue',      'string'],
            ['capacity',   'integer'],
            ['price',      'number'],
            ['sold_out',   'boolean'],
        ]);
        expect([
            $returnType('startDate'),
            $returnType('venue'),
            $returnType('capacity'),
            $returnType('price'),
            $returnType('soldOut'),
        ])->toBe(['?string', '?string', '?int', '?float', 'bool']);
        expect(file_get_contents($path))->toContain("\$value = \$this->meta('start_date');");
    });

    it('gives no accessor that would replace a method of the parent', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['gig'], [
            'class' => 'MakeGigPost',
            'meta' => 'date,link,venue',
            'format' => 'json',
        ]);

        ($this->generatedAttribute)(($this->path)('Models', 'MakeGigPost'), AsPostType::class);

        $class = new ReflectionClass('Theme\\Models\\MakeGigPost');
        $report = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true);

        expect($class->getMethod('date')->getDeclaringClass()->getName())->not->toBe($class->getName());
        expect($class->getMethod('venue')->getDeclaringClass()->getName())->toBe($class->getName());
        expect(count($class->getAttributes(AsPostMeta::class)))->toBe(3);
        expect(implode("\n", $report['notes']))
            ->toContain("No accessor for 'date'")
            ->toContain("No accessor for 'link'")
            ->toContain("post.meta('date')");
    });

    it('rejects a meta type it cannot declare without a schema', function (string $meta) {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['concert'], ['meta' => $meta]);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(glob($this->appPath . '/Models/*.php'))->toBeEmpty();
    })->with(['venue:array', 'venue:date', 'bad key', 'venue,venue']);

    it('rejects an option written without a value', function (string $option, string|bool $value) {
        // WP-CLI passes `true` for `--meta` written without `=<value>`.
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['concert'], [$option => $value]);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toStartWith("Give --{$option} a value");
        expect(glob($this->appPath . '/Models/*.php'))->toBeEmpty();
    })->with([
        ['meta',         true],
        ['meta',         ''],
        ['rewrite-slug', true],
        ['class',        true],
        ['singular',     true],
        ['plural',       ''],
    ]);

    it('gives one accessor to two keys with the same method name', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['fair'], [
            'class' => 'MakeFairPost',
            'meta' => 'start_date,start-date,startdate',
            'format' => 'json',
        ]);

        ($this->generatedAttribute)(($this->path)('Models', 'MakeFairPost'), AsPostType::class);

        $report = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true);

        expect(file_get_contents(($this->path)('Models', 'MakeFairPost')))
            ->toContain("\$value = \$this->meta('start_date');");
        expect(count(new ReflectionClass('Theme\\Models\\MakeFairPost')->getAttributes(AsPostMeta::class)))->toBe(3);
        expect(implode("\n", $report['notes']))
            ->toContain("No accessor for 'start-date': startDate() is already the accessor of 'start_date'")
            ->toContain("No accessor for 'startdate': startdate() is already the accessor of 'start_date'");
    });

    it('names the views the controllers render, and the next steps', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['festival'], [
            'meta' => 'venue',
            'dry-run' => true,
            'format' => 'json',
        ]);

        $report = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true);

        expect($report['notes'])->toHaveCount(4);
        expect($report['notes'][0])->toContain('No template controller claims single-festival');
        expect($report['notes'][1])->toContain('No template controller claims archive-festival');
        expect($report['notes'][2])->toContain('core/post-meta');
        expect($report['notes'][3])->toContain('wp foehn rewrite:flush');
    });

    it('follows the post types the project already declares', function () {
        $conventions = testProjectConventions(testFixturePath('ConventionsApp'));
        $command = new MakePostTypeCommand(
            $this->cli,
            new Scaffolder($this->cli, new ClassFileGenerator(testFixturePath('ConventionsApp')), $conventions),
            $conventions,
        );

        $command(['event'], ['dry-run' => true, 'format' => 'json']);

        $file = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true)['files'][0];

        expect($file['path'])->toBe(testFixturePath('ConventionsApp') . '/Content/Event.php');
        expect($file['status'])->toBe('would-create');
        expect($file['contents'])
            ->toContain('namespace Tests\\Fixtures\\ConventionsApp\\Content;')
            ->toContain('final class Event extends BaseContent')
            ->not->toContain('use Tests\\Fixtures\\ConventionsApp\\Content\\BaseContent;');
    });
});

describe('make:taxonomy', function () {
    it('generates a taxonomy with post types and hierarchy', function () {
        (new MakeTaxonomyCommand($this->cli, $this->scaffolder))(['genre'], [
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
        (new MakeTaxonomyCommand($this->cli, $this->scaffolder))(['mood'], ['class' => 'MakeMoodTerm']);

        /** @var AsTaxonomy $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Taxonomies', 'MakeMoodTerm'), AsTaxonomy::class);

        expect($attribute->postTypes)->toBe(['post'])->and($attribute->hierarchical)->toBeFalse();
    });
});

describe('make:block', function () {
    it('generates a block with the namespaced name and category', function () {
        (new MakeBlockCommand($this->cli, $this->scaffolder))(['hero'], [
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
        (new MakeBlockCommand($this->cli, $this->scaffolder))(['counter'], [
            'class' => 'MakeCounterBlock',
            'interactive' => true,
        ]);

        $contents = file_get_contents(($this->path)('Blocks', 'MakeCounterBlock'));

        /** @var AsBlock $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Blocks', 'MakeCounterBlock'), AsBlock::class);

        expect($attribute->name)->toBe('theme/counter')->and($attribute->interactivity)->toBeTrue();
        expect($contents)->toContain('InteractiveBlockInterface');
    });

    it('points an interactive block to the view script BlockAssets loads', function () {
        (new MakeBlockCommand($this->cli, $this->scaffolder))(['counter'], [
            'class' => 'MakeCounterBlock',
            'interactive' => true,
            'dry-run' => true,
            'format' => 'json',
        ]);

        $report = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true, flags: JSON_THROW_ON_ERROR);

        expect($report['notes'])->toContain('Create the Interactivity API view script: assets/js/blocks/counter.js');
    });
});

describe('make:model', function () {
    it('generates a plain Timber model by default', function () {
        (new MakeModelCommand($this->cli, $this->scaffolder, $this->conventions))(['MakeWidget'], []);

        $path = ($this->path)('Models', 'MakeWidget');
        $contents = file_get_contents($path);

        expect($contents)->toContain('class MakeWidget');
        expect($contents)->not->toContain('#[AsPostType');
        expect($contents)->not->toContain('DummyModel');
    });

    it('registers a post type when asked for one', function () {
        (new MakeModelCommand($this->cli, $this->scaffolder, $this->conventions))(['MakeDoohickey'], [
            'post-type' => true,
            'singular' => 'Doohickey',
            'plural' => 'Doohickeys',
        ]);

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Models', 'MakeDoohickey'), AsPostType::class);

        expect($attribute->name)->toBe('make-doohickey');
        expect($attribute->singular)->toBe('Doohickey');
        expect($attribute->plural)->toBe('Doohickeys');
    });

    it('honours an explicit post type slug', function () {
        (new MakeModelCommand($this->cli, $this->scaffolder, $this->conventions))(['MakeThingamajig'], [
            'post-type' => true,
            'slug' => 'thingy',
        ]);

        /** @var AsPostType $attribute */
        $attribute = ($this->generatedAttribute)(($this->path)('Models', 'MakeThingamajig'), AsPostType::class);

        expect($attribute->name)->toBe('thingy');
    });
});

describe('make:controller', function () {
    it('keeps a single template a string and renders it', function () {
        (new MakeControllerCommand($this->cli, $this->scaffolder))(['MakeSingleProduct'], [
            'templates' => 'single-product',
        ]);

        $path = ($this->path)('Controllers', 'MakeSingleProductController');

        /** @var AsTemplateController $attribute */
        $attribute = ($this->generatedAttribute)($path, AsTemplateController::class);

        expect($attribute->templates)->toBe('single-product');
        expect(file_get_contents($path))->toContain("render('single-product'");
    });

    it('keeps several templates an array and renders the first', function () {
        (new MakeControllerCommand($this->cli, $this->scaffolder))(['MakeArchives'], [
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
        (new MakeContextProviderCommand($this->cli, $this->scaffolder))(['MakeGlobal'], [
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
        (new MakeImageSizeCommand($this->cli, $this->scaffolder))(['MakeHeroWide'], [
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

    it('gives a Twig hint that is valid Twig', function () {
        (new MakeImageSizeCommand($this->cli, $this->scaffolder))(['MakeHeroWide'], [
            'dry-run' => true,
            'format' => 'json',
        ]);

        $report = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true, flags: JSON_THROW_ON_ERROR);

        expect($report['notes'])
            ->toContain(
                "Use in Twig: {{ post.thumbnail.src('make-hero-wide') }}, or call MakeHeroWide::url(\$attachmentId) in PHP.",
            );
    });
});

describe('make:menu and make:shortcode and make:hooks', function () {
    it('generates a menu whose helper reads the new location', function () {
        (new MakeMenuCommand($this->cli, $this->scaffolder))(['FooterMenu'], ['location' => 'footer']);

        $contents = file_get_contents(($this->path)('Menus', 'FooterMenu'));

        expect($contents)->toContain("location: 'footer'");
        expect($contents)->toContain("Timber::get_menu('footer'");
        expect($contents)->not->toContain('DummyMenu');
    });

    it('points to the menus context key in its Twig hint', function () {
        (new MakeMenuCommand($this->cli, $this->scaffolder))(['MainNav'], [
            'location' => 'main-nav',
            'dry-run' => true,
            'format' => 'json',
        ]);

        $report = json_decode(wp_stub_get_calls('wp_cli_line')[0]['args']['message'], true, flags: JSON_THROW_ON_ERROR);

        expect($report['notes'])
            ->toContain("Use in Twig: loop over menus['main-nav'].items, or call MainNav::get() in PHP.");
    });

    it('generates a shortcode with the tag everywhere the stub named it', function () {
        (new MakeShortcodeCommand($this->cli, $this->scaffolder))(['gallery'], ['class' => 'MakeGalleryShortcode']);

        $contents = file_get_contents(($this->path)('Shortcodes', 'MakeGalleryShortcode'));

        expect($contents)->toContain("#[AsShortcode(tag: 'gallery')]");
        expect($contents)->not->toContain('dummy-shortcode');
    });

    it('generates a hooks class named after the request', function () {
        (new MakeHooksCommand($this->cli, $this->scaffolder))(['Theme'], []);

        $contents = file_get_contents(($this->path)('Hooks', 'ThemeHooks'));

        expect($contents)->toContain('class ThemeHooks');
        expect($contents)->not->toContain('DummyHooks');
    });
});

describe('generated files', function () {
    it('are valid PHP for every make command', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['lint-product'], [
            'class' => 'LintPost',
        ]);
        (new MakeTaxonomyCommand($this->cli, $this->scaffolder))(['lint-genre'], ['class' => 'LintTerm']);
        (new MakeBlockCommand($this->cli, $this->scaffolder))(['lint-hero'], ['class' => 'LintBlock']);
        (new MakeBlockCommand($this->cli, $this->scaffolder))(['lint-counter'], [
            'class' => 'LintInteractiveBlock',
            'interactive' => true,
        ]);
        (new MakeControllerCommand($this->cli, $this->scaffolder))(['LintController'], ['templates' => 'single,page']);
        (new MakeContextProviderCommand($this->cli, $this->scaffolder))(['LintProvider'], ['templates' => 'single']);
        (new MakeImageSizeCommand($this->cli, $this->scaffolder))(['LintImageSize'], []);
        (new MakeMenuCommand($this->cli, $this->scaffolder))(['LintMenu'], []);
        (new MakeShortcodeCommand($this->cli, $this->scaffolder))(['lint-tag'], ['class' => 'LintShortcode']);
        (new MakeHooksCommand($this->cli, $this->scaffolder))(['Lint'], []);

        $generated = [];
        $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->appPath));

        /** @var SplFileInfo $entry */
        foreach ($directory as $entry) {
            if ($entry->getExtension() !== 'php') {
                continue;
            }

            $generated[] = $entry->getPathname();
        }

        expect($generated)->toHaveCount(10);

        foreach ($generated as $path) {
            exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

            expect($status)->toBe(0, $path . "\n" . implode("\n", $output));
        }
    });

    it('leave no dummy placeholder behind', function () {
        (new MakePostTypeCommand($this->cli, $this->scaffolder, $this->conventions))(['clean-product'], [
            'class' => 'CleanPost',
        ]);
        (new MakeTaxonomyCommand($this->cli, $this->scaffolder))(['clean-genre'], ['class' => 'CleanTerm']);
        (new MakeBlockCommand($this->cli, $this->scaffolder))(['clean-hero'], ['class' => 'CleanBlock']);
        (new MakeMenuCommand($this->cli, $this->scaffolder))(['CleanMenu'], []);
        (new MakeShortcodeCommand($this->cli, $this->scaffolder))(['clean-tag'], ['class' => 'CleanShortcode']);
        (new MakeHooksCommand($this->cli, $this->scaffolder))(['Clean'], []);

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
