<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\BlockStub;
use Studiometa\Foehn\Console\Stubs\HooksStub;
use Studiometa\Foehn\Console\Stubs\MenuStub;
use Studiometa\Foehn\Console\Stubs\ModelStub;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Tempest\Discovery\SkipDiscovery;

beforeEach(function () {
    $this->root = sys_get_temp_dir() . '/foehn-generator-' . bin2hex(random_bytes(6));
    $this->appPath = $this->root . '/app';

    mkdir($this->appPath, 0o755, true);
    file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Theme\\' => 'app/']]]));

    $this->generator = new ClassFileGenerator($this->appPath);
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->root));
});

describe('ClassFileGenerator paths and namespaces', function () {
    it('puts a class in the requested subdirectory of the app path', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        expect($file->path)->toBe($this->appPath . '/Models/Product.php');
    });

    it('resolves the namespace from the composer psr-4 map', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        expect($file->contents)->toContain('namespace Theme\\Models;');
    });

    it('resolves a nested subdirectory into nested namespace segments', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Fields/Options',
            className: 'ThemeOptions',
        ));

        expect($file->contents)->toContain('namespace Theme\\Fields\\Options;');
    });

    it('falls back to App when no composer.json maps the app path', function () {
        unlink($this->root . '/composer.json');

        $file = new ClassFileGenerator($this->appPath)->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        expect($file->contents)->toContain('namespace App\\Models;');
    });

    it('declares strict types and the requested class name', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        expect($file->contents)
            ->toContain('declare(strict_types=1);')
            ->and($file->contents)
            ->toContain('class Product')
            ->and($file->contents)
            ->not->toContain('ModelStub');
    });

    it('drops the discovery opt-out the stub carries', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        expect($file->contents)
            ->not->toContain(SkipDiscovery::class)->and($file->contents)
            ->not->toContain('SkipDiscovery');
    });
});

describe('ClassFileGenerator attribute rewriting', function () {
    it('sets the named arguments it is given', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: MenuStub::class,
            subdirectory: 'Menus',
            className: 'FooterMenu',
            attributeArguments: ['location' => 'footer', 'description' => 'Footer Navigation'],
        ));

        expect($file->contents)->toContain("#[AsMenu(location: 'footer', description: 'Footer Navigation')]");
    });

    it('keeps the stub value of an argument it is not given', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: BlockStub::class,
            subdirectory: 'Blocks',
            className: 'HeroBlock',
            attributeArguments: ['name' => 'theme/hero', 'title' => 'Hero'],
        ));

        expect($file->contents)
            ->toContain("name: 'theme/hero'")
            ->and($file->contents)
            ->toContain("title: 'Hero'")
            // untouched stub defaults
            ->and($file->contents)
            ->toContain("icon: 'block-default'")
            ->and($file->contents)
            ->toContain("keywords: ['custom']");
    });

    it('sets an argument the stub leaves at its default', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: PostTypeStub::class,
            subdirectory: 'PostTypes',
            className: 'ProductPost',
            attributeArguments: ['name' => 'product', 'taxonomies' => ['product_category', 'product_tag']],
        ));

        expect($file->contents)->toContain("taxonomies: ['product_category', 'product_tag']");
    });

    it('writes a bool argument as a bool', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: PostTypeStub::class,
            subdirectory: 'PostTypes',
            className: 'ProductPost',
            attributeArguments: ['name' => 'product', 'hasArchive' => true],
        ));

        expect($file->contents)->toContain('hasArchive: true');
    });

    it('produces a class whose attribute really carries the new values', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: MenuStub::class,
            subdirectory: 'Menus',
            className: 'GeneratedFooterMenu',
            attributeArguments: ['location' => 'footer', 'description' => 'Footer Navigation'],
            bodyReplacements: ['get' => ["'dummy-menu'" => "'footer'"]],
            replacements: ['DummyMenu' => 'GeneratedFooterMenu'],
        ));

        expect($this->generator->write($file))->toBeTrue();

        require $file->path;

        $attribute = new ReflectionClass(
            'Theme\\Menus\\GeneratedFooterMenu',
        )->getAttributes(AsMenu::class)[0]->newInstance();

        expect($attribute->location)->toBe('footer')->and($attribute->description)->toBe('Footer Navigation');
    });

    it('refuses an argument the attribute does not accept', function () {
        expect(fn() => $this->generator->generate(new GenerationRequest(
            stub: MenuStub::class,
            subdirectory: 'Menus',
            className: 'FooterMenu',
            attributeArguments: ['locatoin' => 'footer'],
        )))
            ->toThrow(RuntimeException::class, 'has no argument(s) named locatoin');
    });

    it('refuses attribute arguments for a stub with no class attribute', function () {
        expect(fn() => $this->generator->generate(new GenerationRequest(
            stub: HooksStub::class,
            subdirectory: 'Hooks',
            className: 'ThemeHooks',
            attributeArguments: ['hook' => 'init'],
        )))
            ->toThrow(RuntimeException::class, 'carries no class attribute');
    });
});

describe('ClassFileGenerator substitution', function () {
    it('replaces a fragment inside the named method only', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: MenuStub::class,
            subdirectory: 'Menus',
            className: 'FooterMenu',
            attributeArguments: ['location' => 'footer'],
            bodyReplacements: ['get' => ["'dummy-menu'" => "'footer'"]],
        ));

        expect($file->contents)
            ->toContain("Timber::get_menu('footer'")
            ->and($file->contents)
            ->not->toContain("'dummy-menu'");
    });

    it('replaces a whole-file sentinel everywhere it appears', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: HooksStub::class,
            subdirectory: 'Hooks',
            className: 'ThemeHooks',
            replacements: ['DummyHooks' => 'ThemeHooks'],
        ));

        expect($file->contents)->not->toContain('DummyHooks')->and($file->contents)->toContain('ThemeHooks');
    });

    it('throws when a body fragment is no longer in the stub', function () {
        expect(fn() => $this->generator->generate(new GenerationRequest(
            stub: MenuStub::class,
            subdirectory: 'Menus',
            className: 'FooterMenu',
            bodyReplacements: ['get' => ["'gone-from-the-stub'" => "'footer'"]],
        )))
            ->toThrow(RuntimeException::class, 'no longer contains the fragment');
    });

    it('throws when the named method is not in the stub', function () {
        expect(fn() => $this->generator->generate(new GenerationRequest(
            stub: MenuStub::class,
            subdirectory: 'Menus',
            className: 'FooterMenu',
            bodyReplacements: ['thereIsNoSuchMethod' => ['a' => 'b']],
        )))
            ->toThrow(RuntimeException::class, 'has no method thereIsNoSuchMethod()');
    });

    it('throws when a whole-file sentinel is no longer in the stub', function () {
        expect(fn() => $this->generator->generate(new GenerationRequest(
            stub: HooksStub::class,
            subdirectory: 'Hooks',
            className: 'ThemeHooks',
            replacements: ['GoneFromTheStub' => 'ThemeHooks'],
        )))
            ->toThrow(RuntimeException::class, 'no longer contains the sentinel');
    });

    it('generates syntactically valid PHP', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: BlockStub::class,
            subdirectory: 'Blocks',
            className: 'HeroBlock',
            attributeArguments: ['name' => 'theme/hero', 'title' => 'Hero', 'category' => 'layout'],
        ));

        $this->generator->write($file);

        exec('php -l ' . escapeshellarg($file->path) . ' 2>&1', $output, $status);

        expect($status)->toBe(0, implode("\n", $output));
    });
});

describe('ClassFileGenerator writing', function () {
    it('writes the file and creates its directory', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Deeply/Nested',
            className: 'Product',
        ));

        expect($this->generator->write($file))->toBeTrue();
        expect(file_get_contents($file->path))->toBe($file->contents);
    });

    it('refuses to overwrite an existing file', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        $this->generator->write($file);
        file_put_contents($file->path, '<?php // hand-edited');

        expect($this->generator->write($file))->toBeFalse();
        expect(file_get_contents($file->path))->toBe('<?php // hand-edited');
    });

    it('overwrites when forced', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        $this->generator->write($file);
        file_put_contents($file->path, '<?php // hand-edited');

        expect($this->generator->write($file, force: true))->toBeTrue();
        expect(file_get_contents($file->path))->toBe($file->contents);
    });

    it('does not touch the disk when only generating', function () {
        $file = $this->generator->generate(new GenerationRequest(
            stub: ModelStub::class,
            subdirectory: 'Models',
            className: 'Product',
        ));

        expect(file_exists($file->path))->toBeFalse();
    });
});
