<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\RewriteRuleDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\InvalidRewriteRuleFixture;
use Tests\Fixtures\PostTypeFixture;
use Tests\Fixtures\RewriteRuleFixture;
use Tests\Fixtures\RewriteRuleWithoutHandlerFixture;

/**
 * @return list<array<string, mixed>>
 */
function discoveredRules(string $fixture): array
{
    $discovery = new RewriteRuleDiscovery(new GenericContainer(), new DiscoveryProblems());

    discoverFixture($discovery, $fixture);

    return array_values(iterator_to_array($discovery->getItems()));
}

describe('RewriteRuleDiscovery', function () {
    it('discovers a rule and the class that answers it', function () {
        $items = discoveredRules(RewriteRuleFixture::class);

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute'])->toBeInstanceOf(AsRewriteRule::class);
        expect($items[0]['attribute']->regex)->toBe('^webhook/stripe/?$');
        expect($items[0]['className'])->toBe(RewriteRuleFixture::class);
        expect($items[0]['handles'])->toBeTrue();
    });

    it('discovers a rule with no handler', function () {
        // A rule that only rewrites onto an existing template is a whole
        // feature, and needs no interface.
        $items = discoveredRules(RewriteRuleWithoutHandlerFixture::class);

        expect($items[0]['handles'])->toBeFalse();
    });

    it('ignores a class without the attribute', function () {
        expect(discoveredRules(PostTypeFixture::class))->toHaveCount(0);
    });

    it('records the query variables that identify a matched request', function () {
        expect(discoveredRules(RewriteRuleFixture::class)[0]['match'])->toBe(['foehn_route' => 'stripe-webhook']);
    });

    it('records a variable whose value is a capture group as required, not equal', function () {
        // `name=$matches[1]` carries whatever the pattern captured, so there is
        // nothing to compare it against — but it still has to be there, and `true`
        // says so. Dropping it instead leaves a rule that sets only captured
        // variables with nothing at all to match on, and it never dispatches.
        expect(discoveredRules(RewriteRuleWithoutHandlerFixture::class)[0]['match'])->toBe([
            'post_type' => 'brochure',
            'name' => true,
        ]);
    });

    it('reports a position add_rewrite_rule does not accept, and skips it', function () {
        $problems = rejectedProblems(
            fn(DiscoveryProblems $problems) => new RewriteRuleDiscovery(new GenericContainer(), $problems),
            InvalidRewriteRuleFixture::class,
        );

        expect($problems[0]['reason'])->toContain("declares after: 'sideways'");
        expect($problems[0]['fix'])->toBe("Set after: 'top' or after: 'bottom' on #[AsRewriteRule].");
    });
});
