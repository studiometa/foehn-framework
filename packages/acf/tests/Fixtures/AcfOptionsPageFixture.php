<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;

#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    menuTitle: 'Theme',
    menuSlug: 'theme-settings',
    capability: 'manage_options',
    position: 59,
    iconUrl: 'dashicons-admin-generic',
    redirect: false,
    autoload: true,
)]
final class AcfOptionsPageFixture implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('theme_settings');
        $builder->addText('site_name');
        $builder->addTextarea('footer_text');

        return $builder;
    }
}
