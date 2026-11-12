<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;
use Tempest\Discovery\SkipDiscovery;

/**
 * DummySeeder - Sample content, written by `wp foehn seed`.
 *
 * run() must be idempotent: find each post, attachment, term or menu before
 * you create it, so a second run updates the content and makes no copy.
 */
#[SkipDiscovery]
#[AsSeeder(order: 10, description: 'Dummy content')]
final class SeederStub implements SeederInterface
{
    public function run(): void
    {
        // Upsert::post() updates the post that has this type and slug, or creates it.
        Upsert::post('post', 'dummy-post', [
            'post_title' => 'Dummy post',
            'post_content' => '',
        ]);
    }
}
