<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;

#[AsAcfOptionsPage(
    pageTitle: 'Full Settings',
    menuTitle: 'Full',
    menuSlug: 'full-settings',
    capability: 'manage_options',
    position: 60,
    iconUrl: 'dashicons-admin-settings',
    redirect: false,
    postId: 'full_settings',
    autoload: false,
    updateButton: 'Save All Settings',
    updatedMessage: 'All settings have been saved.',
)]
final class AcfOptionsPageFullFixture implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('full_settings');
        $builder->addText('option_one');

        return $builder;
    }
}
