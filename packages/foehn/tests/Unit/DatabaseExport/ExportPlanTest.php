<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\DatabaseExportConfig;
use Studiometa\Foehn\DatabaseExport\Anonymizer;
use Studiometa\Foehn\DatabaseExport\ExportPlan;
use Studiometa\Foehn\DatabaseExport\TableRule;

/**
 * @param list<string> $names Table names without the prefix.
 * @return list<string>
 */
function exportTables(array $names, string $prefix = 'wp_'): array
{
    return array_map(static fn(string $name): string => $prefix . $name, $names);
}

const EXPORT_FROM = '2026-01-01 00:00:00';

const CORE_TABLES = [
    'posts',
    'postmeta',
    'terms',
    'term_taxonomy',
    'term_relationships',
    'termmeta',
    'options',
    'links',
    'users',
    'usermeta',
    'comments',
    'commentmeta',
];

describe('ExportPlan', function () {
    it('covers every WordPress core table', function () {
        $plan = ExportPlan::resolve(exportTables(CORE_TABLES), 'wp_', EXPORT_FROM, new DatabaseExportConfig());

        expect($plan->uncovered)->toBe([]);
        expect($plan->structureOnly())->toBe([]);
        expect($plan->presets)->toBe([]);
    });

    it('exports a table no rule covers as structure only, and lists it', function () {
        $plan = ExportPlan::resolve(
            exportTables(['posts', 'newsletter_subscribers']),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(),
        );

        expect($plan->uncovered)->toBe(['wp_newsletter_subscribers']);
        expect($plan->tables['wp_newsletter_subscribers']->rows)->toBeFalse();
        expect($plan->structureOnly())->toBe(['wp_newsletter_subscribers']);
    });

    it('matches rules after the table prefix, whatever it is', function () {
        $plan = ExportPlan::resolve(
            exportTables(['posts', 'users'], 'site_'),
            'site_',
            EXPORT_FROM,
            new DatabaseExportConfig(),
        );

        expect($plan->uncovered)->toBe([]);
        expect($plan->tables['site_users']->columns)->toHaveKey('user_email');
        expect($plan->wheres()['site_posts'])->toContain(EXPORT_FROM);
    });

    it('leaves out transients', function () {
        $plan = ExportPlan::resolve(exportTables(['options']), 'wp_', EXPORT_FROM, new DatabaseExportConfig());

        expect($plan->wheres()['wp_options'])->toContain("NOT LIKE '\\_transient\\_%'")->toContain('site\\_transient');
    });

    it('keeps revisions newer than the window, with their meta and terms', function () {
        $plan = ExportPlan::resolve(exportTables(CORE_TABLES), 'wp_', EXPORT_FROM, new DatabaseExportConfig());
        $wheres = $plan->wheres();

        expect($wheres['wp_posts'])
            ->toBe("(NOT (post_type = 'revision' AND post_modified_gmt < '2026-01-01 00:00:00'))");
        expect($wheres['wp_postmeta'])->toContain('post_id NOT IN (SELECT ID FROM `wp_posts`');
        expect($wheres['wp_term_relationships'])->toContain('object_id NOT IN (SELECT ID FROM `wp_posts`');
    });

    it('anonymizes users, removes personal user meta and anonymizes comments', function () {
        $plan = ExportPlan::resolve(exportTables(CORE_TABLES), 'wp_', EXPORT_FROM, new DatabaseExportConfig());

        expect($plan->tables['wp_users']->columns)->toMatchArray([
            'user_email' => Anonymizer::Email,
            'user_login' => Anonymizer::Username,
            'user_pass' => Anonymizer::Empty,
            'user_activation_key' => Anonymizer::Empty,
        ]);
        expect($plan->wheres()['wp_usermeta'])
            ->toContain("'first_name', 'last_name', 'nickname', 'description', 'session_tokens'")
            ->toContain("NOT LIKE 'billing\\_%'")
            ->toContain("NOT LIKE 'shipping\\_%'");
        expect($plan->tables['wp_comments']->columns)->toMatchArray([
            'comment_author_email' => Anonymizer::Email,
            'comment_author_IP' => Anonymizer::Ip,
        ]);
        expect($plan->tables['wp_comments']->columns)->not->toHaveKey('comment_content');
    });

    it('turns a preset on only when one of its tables exists', function () {
        $without = ExportPlan::resolve(exportTables(CORE_TABLES), 'wp_', EXPORT_FROM, new DatabaseExportConfig());
        $with = ExportPlan::resolve(
            exportTables([...CORE_TABLES, 'woocommerce_order_items']),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(),
        );

        expect($without->presets)->toBe([]);
        expect($without->wheres()['wp_postmeta'])->not->toContain('_billing');

        expect($with->presets)->toBe(['WooCommerce']);
        // The preset adds to the core rule of postmeta rather than replacing it.
        expect($with->wheres()['wp_postmeta'])->toContain('post_id NOT IN')->toContain("NOT LIKE '\\_billing\\_%'");
    });

    it('limits WooCommerce orders to the window and anonymizes them', function () {
        $plan = ExportPlan::resolve(
            exportTables([
                ...CORE_TABLES,
                'wc_orders',
                'wc_order_addresses',
                'wc_orders_meta',
                'woocommerce_order_items',
            ]),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(),
        );
        $wheres = $plan->wheres();

        expect($wheres['wp_wc_orders'])->toContain("date_created_gmt >= '2026-01-01 00:00:00'");
        expect($wheres['wp_wc_order_addresses'])->toContain('order_id NOT IN (SELECT id FROM `wp_wc_orders`');
        expect($wheres['wp_woocommerce_order_items'])->toContain('order_id NOT IN (SELECT id FROM `wp_wc_orders`');
        expect($plan->tables['wp_wc_orders']->columns)->toMatchArray([
            'billing_email' => Anonymizer::Email,
            'ip_address' => Anonymizer::Ip,
        ]);
        expect($plan->tables['wp_wc_order_addresses']->columns)->toMatchArray([
            'email' => Anonymizer::Email,
            'phone' => Anonymizer::Empty,
            'address_1' => Anonymizer::Empty,
        ]);
    });

    it('does not name wc_orders in a condition when that table is not there', function () {
        $plan = ExportPlan::resolve(
            exportTables([...CORE_TABLES, 'woocommerce_order_items', 'woocommerce_order_itemmeta']),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(),
        );

        expect($plan->wheres())->not->toHaveKey('wp_woocommerce_order_items');
        expect(implode(' ', $plan->wheres()))->not->toContain('wc_orders');
    });

    it('exports queues, sessions, form entries and logs as structure only', function () {
        $plan = ExportPlan::resolve(
            exportTables([
                'actionscheduler_actions',
                'actionscheduler_logs',
                'woocommerce_order_items',
                'woocommerce_sessions',
                'gf_form',
                'gf_entry',
                'gf_entry_meta',
                'redirection_404',
                'redirection_items',
            ]),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(),
        );

        expect($plan->structureOnly())->toBe([
            'wp_actionscheduler_actions',
            'wp_actionscheduler_logs',
            'wp_woocommerce_sessions',
            'wp_gf_entry',
            'wp_gf_entry_meta',
            'wp_redirection_404',
        ]);
        expect($plan->uncovered)->toBe([]);
        expect($plan->tables['wp_gf_form']->rows)->toBeTrue();
        expect($plan->tables['wp_redirection_items']->rows)->toBeTrue();
    });

    it('takes the safe, structure-only and column rules of the project config', function () {
        $plan = ExportPlan::resolve(
            exportTables([
                'store_locations',
                'newsletter_subscribers',
                'event_registrations',
                'log_*_unrelated',
                'cache_a',
            ]),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(
                safeTables: ['store_locations', 'cache_*'],
                structureOnlyTables: ['newsletter_subscribers'],
                columns: ['event_registrations' => ['email' => Anonymizer::Email]],
            ),
        );

        expect($plan->tables['wp_store_locations'])->toEqual(new TableRule());
        expect($plan->tables['wp_cache_a'])->toEqual(new TableRule());
        expect($plan->tables['wp_newsletter_subscribers']->rows)->toBeFalse();
        expect($plan->tables['wp_event_registrations']->rows)->toBeTrue();
        expect($plan->tables['wp_event_registrations']->columns)->toBe(['email' => Anonymizer::Email]);
        expect($plan->uncovered)->toBe(['wp_log_*_unrelated']);
    });

    it('never lets a project config make a built-in rule less strict', function () {
        $plan = ExportPlan::resolve(
            exportTables(['users', 'woocommerce_order_items', 'woocommerce_sessions']),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(safeTables: ['users', 'woocommerce_sessions']),
        );

        expect($plan->tables['wp_users']->columns)->toHaveKey('user_email');
        expect($plan->tables['wp_woocommerce_sessions']->rows)->toBeFalse();
    });

    it('lets a project config make a built-in rule stricter', function () {
        $plan = ExportPlan::resolve(
            exportTables(['posts', 'comments']),
            'wp_',
            EXPORT_FROM,
            new DatabaseExportConfig(structureOnlyTables: ['comments'], columns: ['posts' => [
                'post_password' => Anonymizer::Empty,
            ]]),
        );

        expect($plan->tables['wp_comments']->rows)->toBeFalse();
        expect($plan->tables['wp_posts']->columns)->toBe(['post_password' => Anonymizer::Empty]);
        expect($plan->wheres()['wp_posts'])->toContain('revision');
    });
});

describe('TableRule', function () {
    it('describes what an export does with the table', function () {
        expect(new TableRule()->describe())->toBe('all rows');
        expect(TableRule::structureOnly()->describe())->toBe('structure only');
        expect(new TableRule(where: ['a = 1'])->describe())->toBe('filtered');
        expect(new TableRule(where: ['a = 1'], columns: ['b' => Anonymizer::Empty])->describe())
            ->toBe('filtered, anonymized');
    });

    it('joins its conditions with AND', function () {
        expect(new TableRule(where: ['a = 1', 'b = 2 OR c = 3'])->whereClause())->toBe('(a = 1) AND (b = 2 OR c = 3)');
        expect(new TableRule()->whereClause())->toBeNull();
    });
});
