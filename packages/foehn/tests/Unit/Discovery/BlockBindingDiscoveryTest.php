<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Discovery\BlockBindingDiscovery;
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
    $discovery = new BlockBindingDiscovery(new GenericContainer());

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

    it('rejects a source that cannot compute a value', function () {
        expect(fn() => discoveredBindings(InterfacelessBindingFixture::class))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('rejects a source name with no namespace', function () {
        // WordPress refuses it too, through _doing_it_wrong() — which is to say
        // only under WP_DEBUG, and only once the source is already broken.
        expect(fn() => discoveredBindings(UnnamespacedBindingFixture::class))
            ->toThrow(InvalidArgumentException::class, "as in 'theme/reading-time'");
    });
});
