<?php

declare(strict_types=1);

use Studiometa\Foehn\Errors\EntryBuilder;
use Studiometa\Foehn\Errors\RequestContext;
use Studiometa\Foehn\Errors\SourcePaths;
use Studiometa\Foehn\Views\ViewRenderingFailed;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Source;

describe('SourcePaths', function () {
    it('counts the theme directory as own code, but not its vendor and node_modules directories', function () {
        $paths = new SourcePaths('/srv/site/theme');

        expect($paths->isOwn('/srv/site/theme/app/Controllers/PageController.php'))->toBeTrue();
        expect($paths->isOwn('/srv/site/theme/templates/page.twig'))->toBeTrue();
        expect($paths->isOwn('/srv/site/theme/vendor/acme/lib/src/Lib.php'))->toBeFalse();
        expect($paths->isOwn('/srv/site/theme/node_modules/x/index.php'))->toBeFalse();
        expect($paths->isOwn('/srv/site/vendor/timber/timber/src/Timber.php'))->toBeFalse();
        expect($paths->isOwn('/srv/site/theme-child/functions.php'))->toBeFalse();
    });

    it('names a file relative to the directory above the theme', function () {
        $paths = new SourcePaths('/srv/site/theme');

        expect($paths->relative('/srv/site/theme/app/Page.php'))->toBe('theme/app/Page.php');
        expect($paths->relative('/opt/elsewhere/file.php'))->toBe('/opt/elsewhere/file.php');
    });
});

describe('EntryBuilder', function () {
    beforeEach(function () {
        $this->root = dirname(__DIR__, 2);
        $this->request = new RequestContext();
        $this->builder = new EntryBuilder(new SourcePaths($this->root), $this->request);
    });

    it('builds the entry of an exception, with every field', function () {
        $this->request->matchTemplate('single-product', 'App\\Controllers\\ProductController');
        $this->request->renderView('pages/product.twig', ['post', 'site', 'price']);

        $entry = $this->builder->fromThrowable(new RuntimeException('The price is missing.'));

        expect(array_keys($entry))->toBe([
            'time',
            'level',
            'class',
            'message',
            'file',
            'line',
            'twig',
            'previous',
            'stack',
            'method',
            'url',
            'template',
            'controller',
            'view',
            'context',
            'requestId',
        ]);
        expect($entry['time'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/');
        expect($entry['level'])->toBe('exception');
        expect($entry['class'])->toBe(RuntimeException::class);
        expect($entry['message'])->toBe('The price is missing.');
        expect($entry['file'])->toBe('tests/Unit/Errors/EntryBuilderTest.php');
        expect($entry['line'])->toBeInt();
        expect($entry['twig'])->toBeNull();
        expect($entry['method'])->toBe('CLI');
        expect($entry['url'])->toStartWith('wp ');
        expect($entry['template'])->toBe('single-product');
        expect($entry['controller'])->toBe('App\\Controllers\\ProductController');
        expect($entry['view'])->toBe('pages/product.twig');
        expect($entry['context'])->toBe(['post', 'site', 'price']);
        expect($entry['requestId'])->toBeNull();
    });

    it('gives the frames of the theme first and collapses the others into a count', function () {
        $trace = [
            [
                'file' => $this->root . '/src/Page.php',
                'line' => 12,
                'class' => 'App\\Page',
                'type' => '->',
                'function' => 'render',
            ],
            ['file' => '/srv/vendor/timber/timber/src/Timber.php', 'line' => 40, 'function' => 'compile'],
            [
                'file' => $this->root . '/src/Controller.php',
                'line' => 7,
                'class' => 'App\\Controller',
                'type' => '::',
                'function' => 'handle',
            ],
            ['function' => 'call_user_func'],
        ];

        $entry = $this->builder->fromError(
            'warning',
            E_WARNING,
            'Undefined variable $x',
            $this->root . '/src/Page.php',
            12,
            $trace,
        );

        expect($entry['class'])->toBe('E_WARNING');
        expect($entry['level'])->toBe('warning');
        expect($entry['stack'])->toBe([
            'tests/src/Page.php:12 App\\Page->render()',
            'tests/src/Controller.php:7 App\\Controller::handle()',
            '+2 other frames',
        ]);
    });

    it('gives the Twig template and line, and the causes, of a view that failed', function () {
        $twig = new RuntimeError(
            'Variable "price" does not exist.',
            3,
            new Source('', 'partials/card.twig', '/theme/templates/partials/card.twig'),
        );
        $entry = $this->builder->fromThrowable(ViewRenderingFailed::inTwig('pages/product', $twig));

        expect($entry['class'])->toBe(ViewRenderingFailed::class);
        expect($entry['twig'])->toBe('partials/card.twig:3');
        expect($entry['previous'])->toBe([
            RuntimeError::class . ': Variable "price" does not exist in "partials/card.twig" at line 3.',
        ]);
    });

    it('maps a line of a compiled template to the line of the Twig template', function () {
        $environment = new Environment(new ArrayLoader(['card.twig' => "<p>\n{{ title }}\n</p>"]));
        $template = $environment->load('card.twig')->unwrap();
        $file = (string) new ReflectionObject($template)->getFileName();
        $debugInfo = $template->getDebugInfo();
        krsort($debugInfo);
        $codeLine = (int) array_key_first($debugInfo);

        expect(EntryBuilder::twigFromTrace($file, $codeLine, [[
            'object' => $template,
        ]]))->toBe([
            'name' => 'card.twig',
            'path' => '',
            'line' => $debugInfo[$codeLine],
        ]);
        expect(EntryBuilder::twigFromTrace('/other/file.php', $codeLine, [['object' => $template]]))->toBeNull();
    });

    it('cuts a very long message', function () {
        $entry = $this->builder->fromThrowable(new RuntimeException(str_repeat('a', 3000)));

        expect(mb_strlen($entry['message']))->toBe(2001);
    });
});
