/**
 * Føhn — accessibility checks before publication.
 *
 * Adds a panel to the pre-publish sidebar of the editor that lists:
 *
 * - `core/image` blocks with no alternative text, unless marked decorative with the
 *   "Decorative" block style;
 * - images of Føhn block attributes whose attachment has no alternative text;
 * - more than one level 1 heading, and a jump in heading levels (h2 to h4), read
 *   from `core/heading` blocks and the `headingLevel` attributes of Føhn blocks.
 *
 * The PHP side prints the configuration as `window.foehnPrepublishChecks`: which
 * attributes of which Føhn block hold an image or a heading level, and whether the
 * issues block publication. By default they are only warnings.
 *
 * Like the block registrar, this file is generic and has no build step: it is the
 * same file in every project, copied into the web root by the installer, and reads
 * `window.wp`.
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.element || !wp.data || !wp.plugins || !wp.editor || !wp.editor.PluginPrePublishPanel) {
        return;
    }

    const config = window.foehnPrepublishChecks || {};
    const definitions = config.blocks && typeof config.blocks === 'object' ? config.blocks : {};

    const el = wp.element.createElement;
    const useSelect = wp.data.useSelect;
    const PluginPrePublishPanel = wp.editor.PluginPrePublishPanel;
    const Button = wp.components && wp.components.Button;

    // Must match the namespace of the plugin and of the filter, so a project can
    // remove them by name.
    const NAMESPACE = 'foehn/prepublish-checks';

    // The style that marks an image as decorative: its empty alternative text is
    // then intended.
    const DECORATIVE_STYLE = 'decorative';

    // A save to one of these statuses makes the post public.
    const PUBLIC_STATUSES = ['publish', 'future'];

    /**
     * Coerce an attribute value to a list of attachment ids.
     *
     * An `image` attribute holds one id, a `gallery` attribute a list.
     *
     * @param {*} value Attribute value.
     * @return {Array} Numeric ids, possibly empty.
     */
    function toIds(value) {
        return (Array.isArray(value) ? value : [value]).map(Number).filter(function (id) {
            return Number.isInteger(id) && id > 0;
        });
    }

    /**
     * Whether a text is empty once trimmed.
     *
     * @param {*} text Text to read.
     * @return {boolean} True for no text.
     */
    function isBlank(text) {
        return typeof text !== 'string' || text.trim() === '';
    }

    /**
     * The blocks of a tree, depth first, in document order.
     *
     * @param {Array} blocks Blocks of the editor.
     * @param {Array} list   Accumulator.
     * @return {Array} Every block, inner blocks included.
     */
    function flatten(blocks, list) {
        (blocks || []).forEach(function (block) {
            list.push(block);
            flatten(block.innerBlocks, list);
        });

        return list;
    }

    /**
     * The title of a block type, for the messages.
     *
     * @param {string} name Block name.
     * @return {string} Title.
     */
    function blockTitle(name) {
        const type = wp.blocks && wp.blocks.getBlockType ? wp.blocks.getBlockType(name) : null;

        return type && type.title ? type.title : name;
    }

    /**
     * Whether a core image is marked as decorative.
     *
     * @param {Object} attributes Attributes of the image block.
     * @return {boolean} True when the "Decorative" style is applied.
     */
    function isDecorative(attributes) {
        return (' ' + (attributes.className || '') + ' ').indexOf(' is-style-' + DECORATIVE_STYLE + ' ') !== -1;
    }

    /**
     * The ids of every attachment a Føhn block attribute of the content holds.
     *
     * @param {Array} blocks Every block, flattened.
     * @return {Array} Unique attachment ids.
     */
    function attachmentIds(blocks) {
        const ids = [];

        blocks.forEach(function (block) {
            const definition = definitions[block.name];

            Object.keys((definition && definition.images) || {}).forEach(function (key) {
                toIds(block.attributes[key]).forEach(function (id) {
                    if (ids.indexOf(id) === -1) {
                        ids.push(id);
                    }
                });
            });
        });

        return ids;
    }

    /**
     * The heading levels a block renders, in order.
     *
     * @param {Object} block Block.
     * @return {Array} Levels from 1 to 6.
     */
    function headingLevels(block) {
        if (block.name === 'core/heading') {
            return [Number(block.attributes.level) || 2];
        }

        const definition = definitions[block.name];

        return ((definition && definition.headings) || [])
            .map(function (key) {
                return Number(block.attributes[key]);
            })
            .filter(function (level) {
                return Number.isInteger(level) && level >= 1 && level <= 6;
            });
    }

    /**
     * Find the accessibility issues of the content.
     *
     * An attachment whose record is not loaded yet is not reported: its alternative
     * text is not known.
     *
     * @param {Array}       blocks      Blocks of the editor.
     * @param {Array|null}  attachments Attachment records loaded for the content.
     * @return {Array} Issues, as `{ clientId, message }`, in document order.
     */
    function findIssues(blocks, attachments) {
        const issues = [];
        const alts = {};
        let previous = null;
        let h1 = 0;

        (attachments || []).forEach(function (attachment) {
            alts[attachment.id] = attachment.alt_text;
        });

        flatten(blocks, []).forEach(function (block) {
            const attributes = block.attributes || {};
            const definition = definitions[block.name];

            if (block.name === 'core/image' && isBlank(attributes.alt) && !isDecorative(attributes)) {
                issues.push({
                    clientId: block.clientId,
                    message: 'An image has no alternative text. Add one, or apply the "Decorative" style to the image.',
                });
            }

            Object.keys((definition && definition.images) || {}).forEach(function (key) {
                toIds(attributes[key]).forEach(function (id) {
                    if (id in alts && isBlank(alts[id])) {
                        issues.push({
                            clientId: block.clientId,
                            message:
                                'An image of the "' +
                                definition.images[key] +
                                '" field of the "' +
                                blockTitle(block.name) +
                                '" block has no alternative text. Add one in the media library.',
                        });
                    }
                });
            });

            headingLevels(block).forEach(function (level) {
                if (level === 1 && ++h1 === 2) {
                    issues.push({
                        clientId: block.clientId,
                        message: 'The content has more than one level 1 heading. Keep only one.',
                    });
                }

                if (previous !== null && level > previous + 1) {
                    issues.push({
                        clientId: block.clientId,
                        message:
                            'The heading level jumps from H' +
                            previous +
                            ' to H' +
                            level +
                            '. Use H' +
                            (previous + 1) +
                            ' or lower.',
                    });
                }

                previous = level;
            });
        });

        return issues;
    }

    /**
     * The attachment records of the given ids, as far as the store has them.
     *
     * @param {Function} select Store selector.
     * @param {Array}    ids    Attachment ids.
     * @return {Array|null} Records, or null while they load.
     */
    function selectAttachments(select, ids) {
        if (!ids.length) {
            return [];
        }

        return select('core').getEntityRecords('postType', 'attachment', {
            include: ids,
            per_page: ids.length,
        });
    }

    /**
     * The panel of the pre-publish sidebar. It renders nothing when the content has
     * no issue.
     *
     * @return {Object|null} Element.
     */
    function PrepublishChecksPanel() {
        const blocks = useSelect(function (select) {
            return select('core/block-editor').getBlocks();
        }, []);
        const ids = attachmentIds(flatten(blocks, []));
        const attachments = useSelect(
            function (select) {
                return selectAttachments(select, ids);
            },
            [ids.join(',')],
        );
        const issues = findIssues(blocks, attachments);

        if (!issues.length) {
            return null;
        }

        return el(
            PluginPrePublishPanel,
            { title: 'Accessibility', initialOpen: true },
            el(
                'p',
                null,
                config.blockPublication
                    ? 'Fix these issues to publish:'
                    : 'Fix these issues before you publish:',
            ),
            el(
                'ul',
                null,
                issues.map(function (issue, index) {
                    return el('li', { key: index }, issue.message, ' ', renderSelectButton(issue.clientId));
                }),
            ),
        );
    }

    /**
     * A button that selects the block of an issue.
     *
     * @param {string} clientId Block client id.
     * @return {Object|null} Element.
     */
    function renderSelectButton(clientId) {
        if (!Button || !clientId) {
            return null;
        }

        return el(
            Button,
            {
                variant: 'link',
                onClick: function () {
                    wp.data.dispatch('core/block-editor').selectBlock(clientId);
                },
            },
            'Select the block',
        );
    }

    /**
     * Refuse a save that makes the post public while the content has issues.
     *
     * Handler of the `editor.preSavePost` filter. A draft, an autosave and a preview
     * still save. The editor shows the error message in its "Publishing failed"
     * notice.
     *
     * @param {Object} edits   Edits about to be saved.
     * @param {Object} options Save options.
     * @return {Object} The edits, unchanged.
     */
    function refuseInaccessiblePublication(edits, options) {
        if (options && (options.isAutosave || options.isPreview)) {
            return edits;
        }

        const select = wp.data.select;

        if (PUBLIC_STATUSES.indexOf(select('core/editor').getEditedPostAttribute('status')) === -1) {
            return edits;
        }

        const blocks = select('core/block-editor').getBlocks();
        const issues = findIssues(blocks, selectAttachments(select, attachmentIds(flatten(blocks, []))));

        if (issues.length) {
            throw new Error(
                'Fix the accessibility issues of the pre-publish panel first: ' +
                    issues
                        .map(function (issue) {
                            return issue.message;
                        })
                        .join(' '),
            );
        }

        return edits;
    }

    if (wp.blocks && wp.blocks.registerBlockStyle) {
        wp.blocks.registerBlockStyle('core/image', { name: DECORATIVE_STYLE, label: 'Decorative' });
    }

    wp.plugins.registerPlugin(NAMESPACE, { render: PrepublishChecksPanel });

    if (config.blockPublication && wp.hooks) {
        wp.hooks.addFilter('editor.preSavePost', NAMESPACE, refuseInaccessiblePublication);
    }
})(window.wp);
