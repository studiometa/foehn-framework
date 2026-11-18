/**
 * Tests for the accessibility checks before publication.
 *
 * Like the registrar, the script is plain ES2017 reading `window.wp`, so it runs
 * against a fake `window.wp` with nothing but the Node built-ins.
 *
 * Run with `npm run test:editor`.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const SOURCE = readFileSync(new URL('../../resources/js/prepublish-checks.js', import.meta.url), 'utf8');

function createElement(type, props, ...children) {
    return { type, props: props || {}, children };
}

/**
 * Load the script against a fake `window.wp`.
 *
 * @param {Object} config  Payload the PHP side would print as `window.foehnPrepublishChecks`.
 * @param {Object} content Blocks of the editor, attachments of the store and post status.
 */
function load(config, content = {}) {
    const plugins = {};
    const filters = {};
    const styles = [];
    const selected = [];
    const attachments = content.attachments || [];

    const stores = {
        'core/block-editor': { getBlocks: () => content.blocks || [] },
        'core/editor': { getEditedPostAttribute: (name) => (name === 'status' ? content.status || 'draft' : null) },
        core: {
            getEntityRecords(kind, type, query) {
                if (content.loading) {
                    return null;
                }

                return attachments.filter((attachment) => query.include.includes(attachment.id));
            },
        },
    };
    const select = (store) => stores[store];

    const wp = {
        element: { createElement },
        data: {
            select,
            useSelect: (callback) => callback(select),
            dispatch: () => ({ selectBlock: (clientId) => selected.push(clientId) }),
        },
        blocks: {
            getBlockType: (name) => (name === 'theme/hero' ? { title: 'Hero' } : null),
            registerBlockStyle: (name, style) => styles.push({ name, style }),
        },
        components: { Button: 'Button' },
        editor: { PluginPrePublishPanel: 'PluginPrePublishPanel' },
        hooks: {
            addFilter(hook, namespace, callback) {
                filters[hook] = callback;
            },
        },
        plugins: {
            // As Gutenberg does: a name with other characters is refused.
            registerPlugin(name, settings) {
                if (!/^[a-z][a-z0-9-]*$/.test(name)) {
                    return null;
                }

                plugins[name] = settings;

                return settings;
            },
        },
    };

    globalThis.window = { wp, foehnPrepublishChecks: config };

    try {
        vm.runInThisContext(SOURCE);
    } finally {
        delete globalThis.window;
    }

    const plugin = plugins['foehn-prepublish-checks'];

    return { plugin, filters, styles, selected, panel: plugin ? plugin.render() : null };
}

/**
 * The messages the panel lists.
 */
function messages(panel) {
    if (!panel) {
        return [];
    }

    const list = panel.children.find((child) => child.type === 'ul');

    return list.children[0].map((item) => item.children[0]);
}

let next = 0;

function block(name, attributes = {}, innerBlocks = []) {
    return { clientId: 'block-' + ++next, name, attributes, innerBlocks };
}

const HERO = {
    blocks: {
        'theme/hero': { images: { image: 'Background', gallery: 'Gallery' }, headings: ['titleLevel'] },
    },
};

test('the panel renders nothing for accessible content', () => {
    const { panel } = load(HERO, {
        blocks: [
            block('core/heading', { level: 2 }),
            block('core/image', { alt: 'A cat' }),
            block('core/heading', { level: 3 }),
            block('core/heading', { level: 2 }),
        ],
    });

    assert.equal(panel, null);
});

test('a core image with no alternative text is reported', () => {
    const image = block('core/image', { alt: '  ' });
    const { panel } = load(HERO, { blocks: [image] });

    assert.equal(panel.type, 'PluginPrePublishPanel');
    assert.equal(panel.props.title, 'Accessibility');
    assert.deepEqual(messages(panel), [
        'An image has no alternative text. Add one, or apply the "Decorative" style to the image.',
    ]);
});

test('a core image with the decorative style is not reported', () => {
    const { panel, styles } = load(HERO, {
        blocks: [block('core/image', { className: 'is-style-decorative' })],
    });

    assert.equal(panel, null);
    assert.deepEqual(styles, [{ name: 'core/image', style: { name: 'decorative', label: 'Decorative' } }]);
});

test('a Føhn block image whose attachment has no alternative text is reported', () => {
    const { panel } = load(HERO, {
        blocks: [block('theme/hero', { image: 12, gallery: [13, 14] })],
        attachments: [
            { id: 12, alt_text: '' },
            { id: 13, alt_text: 'A dog' },
            { id: 14, alt_text: '' },
        ],
    });

    assert.deepEqual(messages(panel), [
        'An image of the "Background" field of the "Hero" block has no alternative text. Add one in the media library.',
        'An image of the "Gallery" field of the "Hero" block has no alternative text. Add one in the media library.',
    ]);
});

test('an attachment that is not loaded yet is not reported', () => {
    const { panel } = load(HERO, { blocks: [block('theme/hero', { image: 12 })], loading: true });

    assert.equal(panel, null);
});

test('an image attribute of a block without a definition is ignored', () => {
    const { panel } = load(HERO, {
        blocks: [block('theme/other', { image: 12 })],
        attachments: [{ id: 12, alt_text: '' }],
    });

    assert.equal(panel, null);
});

test('a second level 1 heading is reported, in core and Føhn blocks', () => {
    const { panel } = load(HERO, {
        blocks: [block('theme/hero', { titleLevel: 1 }), block('core/heading', { level: 2 }), block('core/heading', { level: 1 })],
    });

    assert.deepEqual(messages(panel), ['The content has more than one level 1 heading. Keep only one.']);
});

test('a jump in heading levels is reported, inner blocks included', () => {
    const { panel } = load(HERO, {
        blocks: [
            block('core/heading', { level: 2 }),
            block('core/group', {}, [block('core/heading', { level: 4 })]),
            block('theme/hero', { titleLevel: 6 }),
        ],
    });

    assert.deepEqual(messages(panel), [
        'The heading level jumps from H2 to H4. Use H3 or lower.',
        'The heading level jumps from H4 to H6. Use H5 or lower.',
    ]);
});

test('a core heading with no level is a level 2 heading', () => {
    const { panel } = load(HERO, { blocks: [block('core/heading'), block('core/heading', { level: 3 })] });

    assert.equal(panel, null);
});

test('an issue selects its block', () => {
    const image = block('core/image');
    const { panel, selected } = load(HERO, { blocks: [image] });
    const button = panel.children.find((child) => child.type === 'ul').children[0][0].children[2];

    button.props.onClick();

    assert.deepEqual(selected, [image.clientId]);
});

test('by default the issues are warnings: publication is not blocked', () => {
    const { filters, panel } = load(HERO, { blocks: [block('core/image')] });

    assert.equal(filters['editor.preSavePost'], undefined);
    assert.equal(panel.children[0].children[0], 'Fix these issues before you publish:');
});

test('the blockPublication option refuses to publish content with issues', async () => {
    const { filters, panel } = load(
        { ...HERO, blockPublication: true },
        { blocks: [block('core/image')], status: 'publish' },
    );

    assert.equal(panel.children[0].children[0], 'Fix these issues to publish:');
    assert.throws(() => filters['editor.preSavePost']({ id: 1 }, {}), /An image has no alternative text/);
});

test('the blockPublication option still saves a draft, an autosave and a preview', () => {
    const edits = { id: 1 };
    const draft = load({ ...HERO, blockPublication: true }, { blocks: [block('core/image')], status: 'draft' });
    const published = load({ ...HERO, blockPublication: true }, { blocks: [block('core/image')], status: 'publish' });

    assert.equal(draft.filters['editor.preSavePost'](edits, {}), edits);
    assert.equal(published.filters['editor.preSavePost'](edits, { isAutosave: true }), edits);
    assert.equal(published.filters['editor.preSavePost'](edits, { isPreview: true }), edits);
});

test('the blockPublication option publishes accessible content', () => {
    const edits = { id: 1 };
    const { filters } = load(
        { ...HERO, blockPublication: true },
        { blocks: [block('core/image', { alt: 'A cat' })], status: 'future' },
    );

    assert.equal(filters['editor.preSavePost'](edits, {}), edits);
});

test('the plugin has a name that registerPlugin accepts', () => {
    const { plugin } = load(HERO);

    assert.equal(typeof plugin.render, 'function');
});

test('without the pre-publish panel API the script does nothing', () => {
    globalThis.window = { wp: { element: {}, data: {}, plugins: {}, editor: {} }, foehnPrepublishChecks: HERO };

    try {
        assert.doesNotThrow(() => vm.runInThisContext(SOURCE));
    } finally {
        delete globalThis.window;
    }
});
