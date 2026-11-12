<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\MenuStub;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Studiometa\Foehn\Console\Stubs\TaxonomyStub;
use Studiometa\Foehn\Console\Stubs\TemplateControllerStub;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Tests\Fixtures\ConventionsApp\Content\BaseContent;
use Tests\Fixtures\ConventionsApp\Http\PageController;
use Timber\Timber;

/**
 * A request for the stub, with the command's defaults.
 *
 * @param class-string $stub
 * @param class-string<\Tempest\Discovery\Discovery>|null $discovery
 */
function conventionsRequest(string $stub, ?string $discovery, string $subdirectory = 'Defaults'): GenerationRequest
{
    return new GenerationRequest(
        stub: $stub,
        subdirectory: $subdirectory,
        className: 'Generated',
        discovery: $discovery,
    );
}

/**
 * Write the Twig files of a theme, relative to its directory.
 */
function conventionsTheme(string $directory, array $files): void
{
    foreach ($files as $file) {
        $path = $directory . '/' . $file;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, '');
    }
}

beforeEach(function () {
    wp_stub_reset();

    $this->theme = sys_get_temp_dir() . '/foehn-conventions-theme-' . bin2hex(random_bytes(6));
    mkdir($this->theme, 0o755, true);
    $GLOBALS['wp_stub_template_directory'] = $this->theme;
    $GLOBALS['wp_stub_stylesheet_directory'] = $this->theme;
    $this->dirname = Timber::$dirname;
    Timber::$dirname = ['templates'];

    $this->conventions = testProjectConventions(testFixturePath('ConventionsApp'));
});

afterEach(function () {
    removeTestDirectory($this->theme);
    unset($GLOBALS['wp_stub_template_directory'], $GLOBALS['wp_stub_stylesheet_directory']);
    Timber::$dirname = $this->dirname;
    tearDownTestContainer();
});

describe('ProjectConventions::place()', function () {
    it('puts a class where most classes of its kind are, in their namespace, under their parent', function () {
        $request = $this->conventions->place(conventionsRequest(PostTypeStub::class, PostTypeDiscovery::class));

        expect($request->subdirectory)
            ->toBe('Content')
            ->and($request->namespace)
            ->toBe('Tests\\Fixtures\\ConventionsApp\\Content')
            ->and($request->parent)
            ->toBe(BaseContent::class);
    });

    it('puts a class beside the others when each has a directory of its own', function () {
        $request = $this->conventions->place(conventionsRequest(MenuStub::class, MenuDiscovery::class));

        expect($request->subdirectory)
            ->toBe('Navigation')
            ->and($request->namespace)
            ->toBe('Tests\\Fixtures\\ConventionsApp\\Navigation')
            ->and($request->parent)
            ->toBeNull();
    });

    it('gives no parent to a stub that extends nothing', function () {
        $request = $this->conventions->place(conventionsRequest(
            TemplateControllerStub::class,
            TemplateControllerDiscovery::class,
        ));

        expect($request->subdirectory)->toBe('Http')->and($request->parent)->toBeNull();
    });

    it('keeps the defaults for a kind the project declares none of', function () {
        $request = conventionsRequest(TaxonomyStub::class, TaxonomyDiscovery::class, 'Taxonomies');

        expect($this->conventions->place($request))->toBe($request);
    });

    it('keeps the defaults of a request that names no kind', function () {
        $request = conventionsRequest(PostTypeStub::class, null, 'Models');

        expect($this->conventions->place($request))->toBe($request);
    });

    it('reads only the classes under the app path', function () {
        // Discovery reads the fixture's post types, but they are not under this app path.
        $request = testProjectConventions(testAppPath(), testFixturePath('ConventionsApp'))->place(conventionsRequest(
            PostTypeStub::class,
            PostTypeDiscovery::class,
            'Models',
        ));

        expect($request->subdirectory)->toBe('Models')->and($request->namespace)->toBeNull();
    });
});

describe('ProjectConventions::view()', function () {
    it('names the file beside the views of the same kind, and the controller that renders it', function () {
        conventionsTheme(
            $this->theme,
            [
                'templates/pages/single.twig',
                'templates/pages/archive.twig',
                'templates/components/card.twig',
            ],
        );

        $single = $this->conventions->view('single', 'event');
        $archive = $this->conventions->view('archive', 'event');

        expect($single->file)
            ->toBe('templates/pages/single-event.twig')
            ->and($single->view)
            ->toBe('pages/single-event')
            ->and($single->controller)
            ->toBe(PageController::class)
            ->and($single->exists)
            ->toBeFalse()
            ->and($single->note())
            ->toBe(
                'Create templates/pages/single-event.twig: '
                . PageController::class
                . ' renders the single-event template, and the theme keeps the views of this kind in pages/.',
            );
        expect($archive->file)->toBe('templates/pages/archive-event.twig');
    });

    it('follows the views of the declared post types', function () {
        conventionsTheme($this->theme, ['templates/single.twig', 'templates/views/single-recipe.twig']);

        expect($this->conventions->view('single', 'event')->file)->toBe('templates/views/single-event.twig');
    });

    it('says when the file is there', function () {
        conventionsTheme($this->theme, ['templates/pages/single.twig', 'templates/pages/single-recipe.twig']);

        $hint = $this->conventions->view('single', 'recipe');

        expect($hint->exists)->toBeTrue()->and($hint->note())->toStartWith('Edit templates/pages/single-recipe.twig');
    });

    it('names the Timber directory that holds the views of the same kind', function () {
        Timber::$dirname = ['templates', 'views'];
        // A root `404.twig` has the view name 404, which PHP keys as an int.
        conventionsTheme($this->theme, ['templates/404.twig', 'views/pages/single.twig']);

        expect($this->conventions->view('single', 'event')->file)->toBe('views/pages/single-event.twig');
    });

    it('falls back to the root of the Timber directory', function () {
        expect($this->conventions->view('single', 'event')->file)->toBe('templates/single-event.twig');
    });

    it('says when no controller claims the template', function () {
        $hint = testProjectConventions(testAppPath())->view('archive', 'event');

        expect($hint->controller)
            ->toBeNull()
            ->and($hint->note())
            ->toContain('No template controller claims archive-event')
            ->toContain('wp foehn make:controller archive-event');
    });
});
