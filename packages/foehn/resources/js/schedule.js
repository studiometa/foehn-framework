/**
 * Føhn — "Schedule" panel of the block inspector.
 *
 * Adds two attributes to every block, core blocks included: `visibleFrom` and
 * `visibleUntil`. Each one is a date and time without a time zone, read in the site
 * time zone by the PHP render filter (`Studiometa\Foehn\Blocks\BlockSchedule`). The
 * block renders to nothing outside these dates, on the front end only.
 *
 * The PHP side adds the same attributes with `register_block_type_args`. They are added
 * here too, because a block registered only in JavaScript would otherwise drop them.
 *
 * Plain ES2017 reading `window.wp`, with no build step, as the block registrar.
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.hooks || !wp.compose || !wp.element || !wp.blockEditor || !wp.components) {
        return;
    }

    const FROM = 'visibleFrom';
    const UNTIL = 'visibleUntil';

    const el = wp.element.createElement;
    const Fragment = wp.element.Fragment;
    const InspectorControls = wp.blockEditor.InspectorControls;
    const PanelBody = wp.components.PanelBody;
    const TextControl = wp.components.TextControl;

    /**
     * Add the two date attributes to a block type.
     */
    function addAttributes(settings) {
        const attributes = Object.assign({}, settings.attributes);

        [FROM, UNTIL].forEach(function (name) {
            if (!attributes[name]) {
                attributes[name] = { type: 'string' };
            }
        });

        return Object.assign({}, settings, { attributes: attributes });
    }

    /**
     * One date field. An empty field removes the attribute.
     */
    function dateControl(props, name, label, help) {
        return el(TextControl, {
            label: label,
            help: help,
            type: 'datetime-local',
            value: props.attributes[name] || '',
            onChange: function (value) {
                const update = {};
                update[name] = value ? value : undefined;
                props.setAttributes(update);
            },
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true,
        });
    }

    const withSchedule = wp.compose.createHigherOrderComponent(function (BlockEdit) {
        return function ScheduleEdit(props) {
            if (!props.isSelected) {
                return el(BlockEdit, props);
            }

            const scheduled = Boolean(props.attributes[FROM] || props.attributes[UNTIL]);

            return el(
                Fragment,
                null,
                el(BlockEdit, props),
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Schedule', initialOpen: scheduled },
                        dateControl(props, FROM, 'Visible from', 'Site time zone. Empty: visible now.'),
                        dateControl(props, UNTIL, 'Visible until', 'Site time zone. Empty: no end.'),
                    ),
                ),
            );
        };
    }, 'withFoehnSchedule');

    wp.hooks.addFilter('blocks.registerBlockType', 'foehn/schedule/attributes', addAttributes);
    wp.hooks.addFilter('editor.BlockEdit', 'foehn/schedule/panel', withSchedule);
})(window.wp);
