<?php

declare(strict_types=1);

use Studiometa\Foehn\Views\Twig\TwigToolkitExtension;
use Twig\Extension\AbstractExtension;

describe('TwigToolkitExtension', function () {
    beforeEach(function () {
        $this->extension = new TwigToolkitExtension();
    });

    it('extends AbstractExtension', function () {
        expect($this->extension)->toBeInstanceOf(AbstractExtension::class);
    });

    it('extends studiometa/twig-toolkit Extension', function () {
        expect($this->extension)->toBeInstanceOf(\Studiometa\TwigToolkit\Extension::class);
    });

    it('registers html_classes function', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('html_classes');
    });

    it('registers html_styles function', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('html_styles');
    });

    it('registers html_attributes function', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('html_attributes');
    });

    it('registers token parsers', function () {
        $tokenParsers = $this->extension->getTokenParsers();

        expect($tokenParsers)->not->toBeEmpty();
    });
});
