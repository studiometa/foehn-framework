<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsBlockCategory;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsRestRoute;
use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Discovery\AttributeCodec;
use Studiometa\Foehn\Jobs\CronInterval;

/**
 * One instance per attribute class, every parameter set to a non-default value so
 * that a field dropped by the codec cannot pass unnoticed.
 *
 * @return array<string, object>
 */
function attributeCodecSamples(): array
{
    return [
        AsAcfBlock::class => new AsAcfBlock(
            name: 'hero',
            title: 'Hero',
            category: 'layout',
            icon: 'cover-image',
            description: 'A hero.',
            keywords: ['hero', 'banner'],
            mode: 'edit',
            supports: ['align' => ['wide']],
            template: 'blocks/hero.twig',
            postTypes: ['page'],
            parent: 'theme/section',
        ),
        AsAcfFieldGroup::class => new AsAcfFieldGroup(
            name: 'group_hero',
            title: 'Hero fields',
            location: [['param' => 'post_type', 'operator' => '==', 'value' => 'page']],
            position: 'side',
            menuOrder: 5,
            style: 'seamless',
            labelPlacement: 'left',
            instructionPlacement: 'field',
            hideOnScreen: ['the_content'],
        ),
        AsAcfOptionsPage::class => new AsAcfOptionsPage(
            pageTitle: 'Theme options',
            menuTitle: 'Options',
            menuSlug: 'theme-options',
            capability: 'manage_options',
            position: 30,
            parentSlug: 'themes.php',
            iconUrl: 'dashicons-admin-generic',
            redirect: false,
            postId: 'options',
            autoload: false,
            updateButton: 'Save',
            updatedMessage: 'Saved.',
        ),
        AsAction::class => new AsAction(hook: 'init', priority: 20, acceptedArgs: 2),
        AsBlock::class => new AsBlock(
            name: 'theme/hero',
            title: 'Hero',
            category: 'theme',
            icon: 'cover-image',
            description: 'A hero.',
            keywords: ['hero'],
            supports: ['align' => ['wide', 'full']],
            parent: 'theme/section',
            ancestor: ['theme/carousel'],
            interactivity: true,
            interactivityNamespace: 'theme/hero-state',
            template: 'blocks/hero.twig',
            allowedBlocks: ['core/heading'],
            innerBlocksTemplate: [['core/heading', ['level' => 2]]],
            innerBlocksTemplateLock: 'insert',
        ),
        AsBlockCategory::class => new AsBlockCategory(slug: 'theme', title: 'Theme', icon: 'star-filled'),
        AsBlockPattern::class => new AsBlockPattern(
            name: 'theme/cta',
            title: 'Call to action',
            categories: ['featured'],
            keywords: ['cta'],
            blockTypes: ['core/post-content'],
            description: 'A CTA.',
            template: 'patterns/cta.twig',
            viewportWidth: 1400,
            inserter: false,
        ),
        AsCliCommand::class => new AsCliCommand(
            name: 'make:thing',
            description: 'Make a thing',
            longDescription: '## OPTIONS',
        ),
        AsContextProvider::class => new AsContextProvider(templates: ['single', 'archive-*'], priority: 30),
        AsCron::class => new AsCron(interval: CronInterval::Weekly, group: 'reports', hook: 'theme_weekly_report'),
        AsFilter::class => new AsFilter(hook: 'the_content', priority: 15, acceptedArgs: 3),
        AsImageSize::class => new AsImageSize(width: 1280, height: 720, crop: true, name: 'hero_wide'),
        AsJob::class => new AsJob(group: 'imports', hook: 'theme_process_import'),
        AsMenu::class => new AsMenu(location: 'primary', description: 'Primary menu'),
        AsPostType::class => new AsPostType(
            name: 'product',
            singular: 'Product',
            plural: 'Products',
            public: false,
            hasArchive: true,
            showInRest: false,
            menuIcon: 'dashicons-cart',
            supports: ['title', 'thumbnail'],
            taxonomies: ['product_category'],
            rewriteSlug: 'products',
            hierarchical: true,
            menuPosition: 25,
            labels: ['add_new' => 'Add product'],
            rewrite: ['slug' => 'products', 'with_front' => false],
        ),
        AsRestRoute::class => new AsRestRoute(
            namespace: 'theme/v1',
            route: '/products',
            method: 'POST',
            permission: 'edit_posts',
            args: ['id' => ['required' => true]],
        ),
        AsShortcode::class => new AsShortcode(tag: 'gallery'),
        AsTaxonomy::class => new AsTaxonomy(
            name: 'product_category',
            postTypes: ['product'],
            singular: 'Category',
            plural: 'Categories',
            public: false,
            hierarchical: true,
            showInRest: false,
            showAdminColumn: false,
            rewriteSlug: 'product-categories',
            labels: ['add_new_item' => 'Add category'],
            rewrite: false,
        ),
        AsTemplateController::class => new AsTemplateController(
            templates: ['single-product', 'archive-*'],
            priority: 40,
        ),
        AsTimberModel::class => new AsTimberModel(name: 'product'),
        AsTwigExtension::class => new AsTwigExtension(priority: 50),
    ];
}

describe('AttributeCodec', function (): void {
    it('covers every attribute Foehn ships', function (): void {
        $shipped = array_map(
            static fn(string $file): string => 'Studiometa\\Foehn\\Attributes\\' . basename($file, '.php'),
            glob(dirname(__DIR__, 3) . '/src/Attributes/*.php') ?: [],
        );

        expect(array_keys(attributeCodecSamples()))->toEqualCanonicalizing($shipped);
    });

    it('rebuilds an equal instance for every attribute', function (): void {
        foreach (attributeCodecSamples() as $class => $attribute) {
            $decoded = AttributeCodec::decode(AttributeCodec::encode($attribute));

            expect($decoded)->toBeInstanceOf($class)->and($decoded)->toEqual($attribute);
        }
    });

    it('encodes every promoted parameter by name', function (): void {
        $encoded = AttributeCodec::encode(new AsMenu(location: 'footer', description: 'Footer menu'));

        expect($encoded)->toBe([
            '__attribute' => AsMenu::class,
            'args' => ['location' => 'footer', 'description' => 'Footer menu'],
        ]);
    });

    it('survives the var_export round trip the cache file uses', function (): void {
        $file = tempnam(sys_get_temp_dir(), 'foehn-codec-') . '.php';

        try {
            $encoded = array_map(AttributeCodec::encode(...), attributeCodecSamples());

            file_put_contents($file, '<?php return ' . var_export($encoded, true) . ';');

            /** @var array<string, array{__attribute: class-string, args: array<string, mixed>}> $restored */
            $restored = require $file;

            foreach (attributeCodecSamples() as $class => $attribute) {
                expect(AttributeCodec::decode($restored[$class]))->toEqual($attribute);
            }
        } finally {
            @unlink($file);
        }
    });

    it('keeps an enum argument an enum through the cache file', function (): void {
        $file = tempnam(sys_get_temp_dir(), 'foehn-codec-') . '.php';

        try {
            $encoded = AttributeCodec::encode(new AsCron(interval: CronInterval::Daily));

            file_put_contents($file, '<?php return ' . var_export($encoded, true) . ';');

            /** @var array{__attribute: class-string, args: array<string, mixed>} $restored */
            $restored = require $file;

            /** @var AsCron $decoded */
            $decoded = AttributeCodec::decode($restored);

            expect($decoded->interval)->toBe(CronInterval::Daily)->and($decoded->intervalSeconds)->toBe(86_400);
        } finally {
            @unlink($file);
        }
    });

    it('recomputes properties that are derived in the constructor', function (): void {
        $decoded = AttributeCodec::decode(AttributeCodec::encode(new AsCron(interval: 900)));

        expect($decoded)->toBeInstanceOf(AsCron::class)->and($decoded->intervalSeconds)->toBe(900);
    });

    it('recognises an encoded attribute', function (): void {
        expect(AttributeCodec::isEncoded(AttributeCodec::encode(new AsShortcode(tag: 'gallery'))))
            ->toBeTrue()
            ->and(AttributeCodec::isEncoded(['className' => 'App\\Blocks\\HeroBlock']))
            ->toBeFalse()
            ->and(AttributeCodec::isEncoded('hero'))
            ->toBeFalse();
    });

    it('refuses an attribute whose constructor parameters are not promoted', function (): void {
        $attribute = new class('hero') {
            public string $name;

            public function __construct(string $name)
            {
                $this->name = $name;
            }
        };

        expect(static fn() => AttributeCodec::encode($attribute))
            ->toThrow(InvalidArgumentException::class, 'is not promoted');
    });

    it('refuses to decode an attribute class that no longer exists', function (): void {
        expect(static fn() => AttributeCodec::decode(['__attribute' => 'Gone\\Attribute', 'args' => []]))
            ->toThrow(InvalidArgumentException::class, 'does not exist');
    });
});
