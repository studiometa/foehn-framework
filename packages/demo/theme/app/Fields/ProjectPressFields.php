<?php

declare(strict_types=1);

namespace Demo\Fields;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;

/**
 * Press — an ACF field group on projects, from studiometa/foehn-acf.
 *
 * A quote from the press, its source, and a link to the article. The link comes from
 * the ButtonLinkBuilder fragment, wrapped in a group so its `link` and `style` fields
 * get their own names. Everything here runs on the free ACF plugin: field groups and
 * fragments need no Pro feature.
 *
 * The values are ACF's, so they are read with get_field(): see Project::press().
 */
#[AsAcfFieldGroup(name: 'project_press', title: 'Press', location: ['post_type' => 'project'], position: 'side')]
final class ProjectPressFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return new FieldsBuilder('project_press')
            ->addTextarea('press_quote', ['label' => 'Quote', 'rows' => 3])
            ->addText('press_source', ['label' => 'Source'])
            ->addGroup('press_link', ['label' => 'Article'])
            ->addFields(new ButtonLinkBuilder(styles: ['link' => 'Text link', 'button' => 'Button'], sizes: null))
            ->endGroup();
    }
}
