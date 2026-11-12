<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Discovery\BlockBindingDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\Bindings\InterfacelessBindingFixture;
use Tests\Fixtures\Bindings\ReadingTimeFixture;
use Tests\Fixtures\Bindings\UnnamespacedBindingFixture;
use Tests\Fixtures\PostTypeFixture;

/**
 * @return list<array<string, mixed>>
 */
function discoveredBindings(string $fixture): array
{
    $discovery = new BlockBindingDiscovery(new GenericContainer(), new DiscoveryProblems());

    discoverFixture($discovery, $fixture);

    return array_values(iterator_to_array($discovery->getItems()));
}

describe('BlockBindingDiscovery', function () {
    it('discovers a source and the class that computes it', function () {
        $items = discoveredBindings(ReadingTimeFixture::class);

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute'])->toBeInstanceOf(AsBlockBinding::class);
        expect($items[0]['attribute']->name)->toBe('theme/reading-time');
        expect($items[0]['attribute']->usesContext)->toBe(['postId']);
        expect($items[0]['className'])->toBe(ReadingTimeFixture::class);
    });

    it('ignores a class without the attribute', function () {
        expect(discoveredBindings(PostTypeFixture::class))->toHaveCount(0);
    });

    it('reports a source that cannot compute a value, and skips it', function () {
        $problems = rejectedProblems(
            fn(DiscoveryProblems $problems) => new BlockBindingDiscovery(new GenericContainer(), $problems),
            InterfacelessBindingFixture::class,
        );

        expect($problems)->toHaveCount(1);
        expect($problems[0]['attribute'])->toBe('#[AsBlockBinding]');
        expect($problems[0]['reason'])
            ->toContain('does not implement Studiometa\\Foehn\\Contracts\\BlockBindingInterface');
        expect($problems[0]['fix'])
            ->toBe(
                'Declare '
                . InterfacelessBindingFixture::class
                . ' with `implements \\Studiometa\\Foehn\\Contracts\\BlockBindingInterface`.',
            );
    });

    it('reports a source name with no namespace, and skips it', function () {
        // WordPress refuses it too, through _doing_it_wrong() — which is to say
        // only under WP_DEBUG, and only once the source is already broken.
        $problems = rejectedProblems(
            fn(DiscoveryProblems $problems) => new BlockBindingDiscovery(new GenericContainer(), $problems),
            UnnamespacedBindingFixture::class,
        );

        expect($problems[0]['reason'])->toContain("names the source 'reading-time', which has no namespace");
        expect($problems[0]['fix'])->toBe("Name the source 'theme/reading-time' on #[AsBlockBinding].");
    });
});
