<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\Commands\RewriteFlushCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\RewriteRuleDiscovery;

beforeEach(function () {
    wp_stub_reset();

    $GLOBALS['wp_stub_options']['permalink_structure'] = '/%postname%/';

    $this->command = new RewriteFlushCommand(new WpCli());
});

describe('rewrite:flush', function () {
    it('flushes the rules', function () {
        ($this->command)([], []);

        expect(wp_stub_get_calls('flush_rewrite_rules'))->toHaveCount(1);
        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
    });

    it('forgets the hash, so the next request registers the rules again', function () {
        $GLOBALS['wp_stub_options'][RewriteRuleDiscovery::HASH_OPTION] = 'stale';

        ($this->command)([], []);

        expect(get_option(RewriteRuleDiscovery::HASH_OPTION))->toBeFalse();
    });

    it('says so when the site uses plain permalinks', function () {
        // No flush makes a rule match under plain permalinks, and someone will
        // otherwise test on a fresh install and file a bug.
        $GLOBALS['wp_stub_options']['permalink_structure'] = '';

        ($this->command)([], []);

        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
        expect(wp_stub_get_calls('flush_rewrite_rules'))->toHaveCount(1);
    });

    it('says nothing about permalinks when they are set', function () {
        ($this->command)([], []);

        expect(wp_stub_get_calls('wp_cli_warning'))->toBeEmpty();
    });
});
