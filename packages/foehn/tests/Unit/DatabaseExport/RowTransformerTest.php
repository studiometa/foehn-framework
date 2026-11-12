<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\DatabaseExportConfig;
use Studiometa\Foehn\DatabaseExport\ExportPlan;
use Studiometa\Foehn\DatabaseExport\RowTransformer;

function userRow(int $id, string $email): array
{
    return [
        'ID' => (string) $id,
        'user_login' => 'jane',
        'user_pass' => '$wp$2y$10$abcdefghijklmnopqrstuv',
        'user_nicename' => 'jane-doe',
        'user_email' => $email,
        'user_url' => 'https://jane.example.com',
        'user_registered' => '2024-01-01 00:00:00',
        'user_activation_key' => '1700000000:$P$abcdef',
        'user_status' => '0',
        'display_name' => 'Jane Doe',
    ];
}

function rowTransformer(DatabaseExportConfig $config = new DatabaseExportConfig()): RowTransformer
{
    $plan = ExportPlan::resolve(['wp_users', 'wp_comments', 'wp_posts'], 'wp_', '2026-01-01 00:00:00', $config);

    return new RowTransformer($plan->tables, $config);
}

describe('RowTransformer', function () {
    it('replaces every personal column of a user, from the user ID', function () {
        $row = rowTransformer()('wp_users', userRow(42, 'jane@example.com'));

        expect($row)->toBe([
            'ID' => '42',
            'user_login' => 'user-42',
            'user_pass' => '',
            'user_nicename' => 'user-42',
            'user_email' => 'user-42@example.invalid',
            'user_url' => '',
            'user_registered' => '2024-01-01 00:00:00',
            'user_activation_key' => '',
            'user_status' => '0',
            'display_name' => 'User 42',
        ]);
    });

    it('lets a user of an allowed domain keep their email and login, but not their password', function () {
        $transform = rowTransformer(new DatabaseExportConfig(allowedEmailDomains: ['example.com']));

        $row = $transform('wp_users', userRow(1, 'dev@example.com'));

        expect($row['user_email'])->toBe('dev@example.com');
        expect($row['user_login'])->toBe('jane');
        expect($row['display_name'])->toBe('Jane Doe');
        expect($row['user_pass'])->toBe('');
        expect($row['user_activation_key'])->toBe('');
        expect($row['user_url'])->toBe('');
    });

    it('anonymizes a comment author and keeps the comment', function () {
        $row = rowTransformer()('wp_comments', [
            'comment_ID' => '7',
            'comment_author' => 'Jane Doe',
            'comment_author_email' => 'jane@example.com',
            'comment_author_url' => 'https://jane.example.com',
            'comment_author_IP' => '203.0.113.9',
            'comment_content' => 'Nice post.',
            'comment_agent' => 'Mozilla/5.0',
        ]);

        expect($row)->toBe([
            'comment_ID' => '7',
            'comment_author' => 'User 7',
            'comment_author_email' => 'user-7@example.invalid',
            'comment_author_url' => '',
            'comment_author_IP' => '192.0.2.1',
            'comment_content' => 'Nice post.',
            'comment_agent' => '',
        ]);
    });

    it('leaves a row of a table without column rules as it is', function () {
        $row = ['ID' => '1', 'post_title' => 'Hello'];

        expect(rowTransformer()('wp_posts', $row))->toBe($row);
        expect(rowTransformer()('wp_unknown', $row))->toBe($row);
    });

    it('skips a column the table does not have', function () {
        $row = rowTransformer()('wp_users', ['ID' => '3', 'user_email' => 'a@example.net']);

        expect($row)->toBe(['ID' => '3', 'user_email' => 'user-3@example.invalid']);
    });
});
