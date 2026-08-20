<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Hooks\StudiometaUi;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

/**
 * studiometa/ui is a `suggest`, not a dependency, so the framework's own suite runs
 * without it. That is the case worth testing hardest: a site that opted the hook in
 * and has not installed the package must render rather than fatal.
 */
describe('StudiometaUi', function () {
    it('registers on the filter Timber builds its environment with', function () {
        $method = new ReflectionMethod(StudiometaUi::class, 'register');
        $filter = $method->getAttributes(AsFilter::class)[0]->newInstance();

        expect($filter->hook)->toBe('timber/twig');
    });

    it('does nothing when the package is not installed', function () {
        $twig = new Environment(new FilesystemLoader([]));

        $returned = new StudiometaUi()->register($twig);

        expect($returned)->toBe($twig);
        expect($returned->hasExtension(Studiometa\TwigToolkit\Extension::class))->toBeFalse();
    })->skip(
        class_exists(Studiometa\Ui\Extension::class),
        'studiometa/ui is installed here, so the absent-package path cannot run',
    );

    it('leaves a loader it cannot add paths to alone', function () {
        // The namespaces are registered on the loader itself. A project using an
        // ArrayLoader, or a chain, has nothing to add them to — and a TypeError
        // there would take down every page rather than lose a component.
        $twig = new Environment(new ArrayLoader([]));

        expect(new StudiometaUi()->register($twig))->toBe($twig);
    });

    it('registers the ui and svg namespaces when the package is installed', function () {
        $loader = new FilesystemLoader([]);
        $twig = new Environment($loader);

        new StudiometaUi()->register($twig);

        expect($loader->getNamespaces())->toContain('ui');
        expect($loader->getNamespaces())->toContain('svg');
    })->skip(
        !class_exists(Studiometa\Ui\Extension::class),
        'studiometa/ui is a suggest, so this only runs where it is installed',
    );
});
