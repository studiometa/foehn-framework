<?php

declare(strict_types=1);

use Studiometa\WPTempest\Discovery\DiscoveryRunner;

describe('DiscoveryRunner', function () {
    it('returns all discovery classes', function () {
        $classes = DiscoveryRunner::getAllDiscoveryClasses();

        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\HookDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\PostTypeDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\TaxonomyDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\ShortcodeDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\CliCommandDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\AcfBlockDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\BlockDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\BlockPatternDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\ViewComposerDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\TemplateControllerDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\RestRouteDiscovery::class);
        expect($classes)->toContain(\Studiometa\WPTempest\Discovery\TimberModelDiscovery::class);
    });

    it('returns discovery phases', function () {
        $phases = DiscoveryRunner::getDiscoveryPhases();

        expect($phases)->toHaveKeys(['early', 'main', 'late']);

        // Early phase
        expect($phases['early'])->toContain(\Studiometa\WPTempest\Discovery\HookDiscovery::class);
        expect($phases['early'])->toContain(\Studiometa\WPTempest\Discovery\ShortcodeDiscovery::class);
        expect($phases['early'])->toContain(\Studiometa\WPTempest\Discovery\CliCommandDiscovery::class);
        expect($phases['early'])->toContain(\Studiometa\WPTempest\Discovery\TimberModelDiscovery::class);

        // Main phase
        expect($phases['main'])->toContain(\Studiometa\WPTempest\Discovery\PostTypeDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\WPTempest\Discovery\TaxonomyDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\WPTempest\Discovery\AcfBlockDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\WPTempest\Discovery\BlockDiscovery::class);
        expect($phases['main'])->toContain(\Studiometa\WPTempest\Discovery\BlockPatternDiscovery::class);

        // Late phase
        expect($phases['late'])->toContain(\Studiometa\WPTempest\Discovery\ViewComposerDiscovery::class);
        expect($phases['late'])->toContain(\Studiometa\WPTempest\Discovery\TemplateControllerDiscovery::class);
        expect($phases['late'])->toContain(\Studiometa\WPTempest\Discovery\RestRouteDiscovery::class);
    });

    it('has correct number of discoveries in each phase', function () {
        $phases = DiscoveryRunner::getDiscoveryPhases();

        expect($phases['early'])->toHaveCount(4);
        expect($phases['main'])->toHaveCount(5);
        expect($phases['late'])->toHaveCount(3);
    });

    it('all discovery classes total matches phase sum', function () {
        $phases = DiscoveryRunner::getDiscoveryPhases();
        $all = DiscoveryRunner::getAllDiscoveryClasses();

        $phaseTotal = count($phases['early']) + count($phases['main']) + count($phases['late']);

        expect(count($all))->toBe($phaseTotal);
    });

    it('all discovery classes implement WpDiscovery', function () {
        $classes = DiscoveryRunner::getAllDiscoveryClasses();

        foreach ($classes as $class) {
            expect(is_subclass_of($class, \Studiometa\WPTempest\Discovery\WpDiscovery::class))
                ->toBeTrue("Expected {$class} to implement WpDiscovery");
        }
    });
});
