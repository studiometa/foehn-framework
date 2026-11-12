<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

/**
 * The rules Føhn ships: WordPress core tables, and a short list of plugin presets.
 *
 * Table names are given without the prefix. Conditions name other tables with the
 * real prefix, so they are built for one export, with its prefix and its window.
 */
final readonly class BuiltInRules
{
    /**
     * Order meta keys that hold an address, an email, a phone, an IP or a browser.
     *
     * WooCommerce keeps them in `postmeta` (legacy order storage) and in
     * `wc_orders_meta` (address indexes used for search).
     */
    private const string ORDER_PERSONAL_META = "meta_key NOT LIKE '\\_billing\\_%' AND meta_key NOT LIKE '\\_shipping\\_%' AND meta_key NOT IN ('_customer_ip_address', '_customer_user_agent')";

    /**
     * @param string $prefix The table prefix.
     * @param string $from The start of the window, as `Y-m-d H:i:s` in UTC.
     */
    public function __construct(
        private string $prefix,
        private string $from,
    ) {}

    /**
     * @return array<string, TableRule>
     */
    public function core(): array
    {
        $oldRevisions = sprintf(
            "SELECT ID FROM `%sposts` WHERE post_type = 'revision' AND post_modified_gmt < '%s'",
            $this->prefix,
            $this->from,
        );

        return [
            'posts' => new TableRule(where: [
                "NOT (post_type = 'revision' AND post_modified_gmt < '{$this->from}')",
            ]),
            'postmeta' => new TableRule(where: ["post_id NOT IN ({$oldRevisions})"]),
            'term_relationships' => new TableRule(where: ["object_id NOT IN ({$oldRevisions})"]),
            'terms' => new TableRule(),
            'term_taxonomy' => new TableRule(),
            'termmeta' => new TableRule(),
            'links' => new TableRule(),
            'options' => new TableRule(where: [
                "option_name NOT LIKE '\\_transient\\_%' AND option_name NOT LIKE '\\_site\\_transient\\_%'",
            ]),
            'users' => new TableRule(
                columns: [
                    'user_login' => Anonymizer::Username,
                    'user_nicename' => Anonymizer::Username,
                    'user_email' => Anonymizer::Email,
                    'display_name' => Anonymizer::Name,
                    'user_url' => Anonymizer::Empty,
                    'user_pass' => Anonymizer::Empty,
                    'user_activation_key' => Anonymizer::Empty,
                ],
                seedColumn: 'ID',
                emailColumn: 'user_email',
            ),
            'usermeta' => new TableRule(where: [
                "meta_key NOT IN ('first_name', 'last_name', 'nickname', 'description', 'session_tokens')",
                "meta_key NOT LIKE 'billing\\_%' AND meta_key NOT LIKE 'shipping\\_%'",
            ]),
            'comments' => new TableRule(
                where: ["comment_approved NOT IN ('spam', 'trash')"],
                columns: [
                    'comment_author' => Anonymizer::Name,
                    'comment_author_email' => Anonymizer::Email,
                    'comment_author_url' => Anonymizer::Empty,
                    'comment_author_IP' => Anonymizer::Ip,
                    'comment_agent' => Anonymizer::Empty,
                ],
                seedColumn: 'comment_ID',
                emailColumn: 'comment_author_email',
            ),
            'commentmeta' => new TableRule(where: [
                sprintf(
                    "comment_id NOT IN (SELECT comment_ID FROM `%scomments` WHERE comment_approved IN ('spam', 'trash'))",
                    $this->prefix,
                ),
            ]),
        ];
    }

    /**
     * @param list<string> $tables Real table names, so a condition never names a table
     *                             that is not there.
     * @return list<Preset>
     */
    public function presets(array $tables): array
    {
        return [$this->wooCommerce($tables), $this->gravityForms(), $this->logs()];
    }

    /**
     * @param list<string> $tables
     */
    private function wooCommerce(array $tables): Preset
    {
        $orders = $this->prefix . 'wc_orders';
        $hasOrders = in_array($orders, $tables, true);
        $oldOrders = "SELECT id FROM `{$orders}` WHERE date_created_gmt < '{$this->from}'";

        // Rows that belong to an order outside the window go with it. Only for orders
        // in `wc_orders`: legacy orders are posts, and they are kept.
        $inWindow = static fn(string $column): array => $hasOrders ? ["{$column} NOT IN ({$oldOrders})"] : [];

        $rules = [
            // Legacy order storage keeps addresses in post meta.
            'postmeta' => new TableRule(where: [self::ORDER_PERSONAL_META]),
            'wc_orders' => new TableRule(
                where: ["date_created_gmt >= '{$this->from}'"],
                columns: [
                    'billing_email' => Anonymizer::Email,
                    'ip_address' => Anonymizer::Ip,
                    'user_agent' => Anonymizer::Empty,
                    'customer_note' => Anonymizer::Empty,
                ],
                seedColumn: 'id',
                emailColumn: 'billing_email',
            ),
            'wc_order_addresses' => new TableRule(
                where: $inWindow('order_id'),
                columns: [
                    'first_name' => Anonymizer::Name,
                    'last_name' => Anonymizer::Empty,
                    'company' => Anonymizer::Empty,
                    'address_1' => Anonymizer::Empty,
                    'address_2' => Anonymizer::Empty,
                    'postcode' => Anonymizer::Empty,
                    'email' => Anonymizer::Email,
                    'phone' => Anonymizer::Empty,
                ],
                seedColumn: 'id',
                emailColumn: 'email',
            ),
            'wc_orders_meta' => new TableRule(where: [...$inWindow('order_id'), self::ORDER_PERSONAL_META]),
            'wc_order_operational_data' => new TableRule(where: $inWindow('order_id')),
            'woocommerce_order_items' => new TableRule(where: $inWindow('order_id')),
            'woocommerce_order_itemmeta' => new TableRule(
                where: $hasOrders
                    ? [sprintf(
                        'order_item_id NOT IN (SELECT order_item_id FROM `%swoocommerce_order_items` WHERE order_id IN (%s))',
                        $this->prefix,
                        $oldOrders,
                    )]
                    : [],
            ),
            'wc_customer_lookup' => new TableRule(
                columns: [
                    'username' => Anonymizer::Username,
                    'first_name' => Anonymizer::Name,
                    'last_name' => Anonymizer::Empty,
                    'email' => Anonymizer::Email,
                    'postcode' => Anonymizer::Empty,
                ],
                seedColumn: 'customer_id',
                emailColumn: 'email',
            ),
            // Settings and catalogue data the shop needs to run.
            'woocommerce_attribute_taxonomies' => new TableRule(),
            'wc_product_meta_lookup' => new TableRule(),
            'wc_product_attributes_lookup' => new TableRule(),
            'wc_tax_rate_classes' => new TableRule(),
            'woocommerce_tax_rates' => new TableRule(),
            'woocommerce_tax_rate_locations' => new TableRule(),
            'woocommerce_shipping_zones' => new TableRule(),
            'woocommerce_shipping_zone_locations' => new TableRule(),
            'woocommerce_shipping_zone_methods' => new TableRule(),
            // Queues, sessions, analytics and secrets. WooCommerce rebuilds the analytics.
            'actionscheduler_*' => TableRule::structureOnly(),
            'woocommerce_sessions' => TableRule::structureOnly(),
            'woocommerce_api_keys' => TableRule::structureOnly(),
            'woocommerce_payment_tokens' => TableRule::structureOnly(),
            'woocommerce_payment_tokenmeta' => TableRule::structureOnly(),
            'wc_webhooks' => TableRule::structureOnly(),
            'wc_download_log' => TableRule::structureOnly(),
            'wc_order_stats' => TableRule::structureOnly(),
            'wc_order_product_lookup' => TableRule::structureOnly(),
            'wc_order_tax_lookup' => TableRule::structureOnly(),
            'wc_order_coupon_lookup' => TableRule::structureOnly(),
        ];

        return new Preset('WooCommerce', ['woocommerce_order_items', 'wc_orders'], $rules);
    }

    private function gravityForms(): Preset
    {
        return new Preset(
            'Gravity Forms',
            ['gf_form'],
            [
                'gf_form' => new TableRule(),
                'gf_form_meta' => new TableRule(),
                'gf_form_revisions' => new TableRule(),
                'gf_form_view' => new TableRule(),
                'gf_entry' => TableRule::structureOnly(),
                'gf_entry_meta' => TableRule::structureOnly(),
                'gf_entry_notes' => TableRule::structureOnly(),
                'gf_draft_submissions' => TableRule::structureOnly(),
            ],
        );
    }

    /**
     * Logs and search indexes: large, personal, and rebuilt by their plugin.
     */
    private function logs(): Preset
    {
        $structureOnly = [
            // Redirection
            'redirection_404',
            'redirection_logs',
            // Relevanssi
            'relevanssi',
            'relevanssi_log',
            // Yoast SEO indexables, rebuilt by `wp yoast index`
            'yoast_indexable',
            'yoast_indexable_hierarchy',
            'yoast_seo_links',
            // Wordfence
            'wf*',
            // Simple History
            'simple_history',
            'simple_history_contexts',
        ];

        return new Preset(
            'Logs and indexes',
            $structureOnly,
            array_fill_keys($structureOnly, TableRule::structureOnly())
            + [
                'redirection_items' => new TableRule(),
                'redirection_groups' => new TableRule(),
                'yoast_primary_term' => new TableRule(),
                'yoast_migrations' => new TableRule(),
            ],
        );
    }
}
