/**
 * Regression tests for the generic block editor registrar.
 *
 * The registrar is copied verbatim into every project's web root by the installer, so
 * a mistake in it takes out the block editor of every install and cannot be hotfixed
 * without a new Foehn release. It is plain ES2017 reading `window.wp` globals, which
 * makes it testable with nothing but the Node built-ins: no bundler, no npm
 * dependency, no jsdom.
 *
 * Run with `npm run test:editor`.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const SOURCE = readFileSync(new URL('../../resources/js/editor.js', import.meta.url), 'utf8');

/**
 * Stand-in for `wp.element.createElement`: keeps the tree inspectable.
 */
function createElement(type, props, ...children) {
    return { type, props: props || {}, children };
}

/**
 * Load the registrar against a fake `window.wp` and return what it registered.
 *
 * @param {Array} definitions Payload the PHP side would print as `window.foehnBlocks`.
 * @return {Object} Registered block settings, keyed by block name.
 */
function register(definitions, entities = {}) {
    const registered = {};

    const InnerBlocks = function InnerBlocks() {};
    InnerBlocks.Content = function Content() {};

    // `records` is keyed by post type, `postTypes` lists what the site exposes.
    // Enough for the controls that read entity records; the selector shapes match
    // what @wordpress/core-data returns.
    const records = entities.records || {};
    const store = {
        getEntityRecords(kind, type, query) {
            const all = records[type] || [];

            if (query && Array.isArray(query.include)) {
                return all.filter((record) => query.include.includes(record.id));
            }

            return all;
        },
        getEntityRecord(kind, type, id) {
            return (records[type] || []).find((record) => record.id === id) || null;
        },
        getPostTypes() {
            return entities.postTypes || [];
        },
    };

    const wp = {
        blocks: {
            registerBlockType(name, settings) {
                registered[name] = settings;
            },
        },
        element: {
            createElement,
            cloneElement(element, props) {
                return { ...element, props: { ...element.props, ...props } };
            },
            Fragment: 'Fragment',
            // One render per call, so the setter only has to exist: nothing in these
            // tests depends on a state change surviving to a second render.
            useState(initial) {
                return [initial, () => {}];
            },
        },
        data: {
            useSelect(callback) {
                return callback(() => store);
            },
        },
        blockEditor: {
            InspectorControls: 'InspectorControls',
            InnerBlocks,
            MediaUpload: 'MediaUpload',
            MediaUploadCheck: 'MediaUploadCheck',
            useBlockProps: () => ({ className: 'wp-block' }),
        },
        components: {
            BaseControl: 'BaseControl',
            Button: 'Button',
            Disabled: 'Disabled',
            PanelBody: 'PanelBody',
            ComboboxControl: 'ComboboxControl',
            SelectControl: 'SelectControl',
            TextControl: 'TextControl',
            TextareaControl: 'TextareaControl',
            ToggleControl: 'ToggleControl',
        },
        serverSideRender: { ServerSideRender: 'ServerSideRender' },
    };

    // Run in this context rather than a fresh one: a new context has its own
    // intrinsics, and every object the registrar builds would then fail a strict
    // deep comparison against a host object for reasons that say nothing about the code.
    globalThis.window = { wp, foehnBlocks: definitions, console };

    try {
        vm.runInThisContext(SOURCE);
    } finally {
        delete globalThis.window;
    }

    return { registered, InnerBlocks };
}

/**
 * Collect every element of a tree, depth first.
 */
function flatten(node, found = []) {
    if (Array.isArray(node)) {
        node.forEach((child) => flatten(child, found));

        return found;
    }

    if (!node || typeof node !== 'object' || !('type' in node)) {
        return found;
    }

    found.push(node);
    flatten(node.children, found);

    return found;
}

/**
 * Render a block's `edit` and return the elements it produced.
 */
function edit(settings, attributes) {
    const changes = [];
    const tree = settings.edit({
        attributes,
        setAttributes: (patch) => changes.push(patch),
    });

    return { elements: flatten(tree), changes };
}

/**
 * Render the component a control returned, and flatten what it produced.
 *
 * `createElement` above only records the call, so a control that returns
 * `el(GalleryControl, props)` yields an element whose type is the function and
 * whose body never ran. Calling it here is what the real renderer would do.
 */
function renderComponent(element) {
    assert.equal(typeof element.type, 'function', 'expected a component element');

    return flatten(element.type(element.props));
}

/**
 * The first element of a given type in a tree.
 */
function find(elements, type) {
    return elements.find((element) => element.type === type);
}

const field = (control, type, extra) => ({
    control,
    type: type || null,
    label: 'Label',
    help: null,
    options: null,
    ...extra,
});

test('registers only behaviour, never metadata the server already bootstrapped', () => {
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }]);

    assert.deepEqual(Object.keys(registered), ['test/hero']);
    assert.deepEqual(Object.keys(registered['test/hero']), ['edit']);
});

test('a non container gets no save, so it stays fully dynamic', () => {
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }]);

    assert.equal(registered['test/hero'].save, undefined);
});

test('a container saves InnerBlocks.Content so the inner markup reaches the template', () => {
    const { registered, InnerBlocks } = register([
        { name: 'test/section', attributes: {}, innerBlocks: { allowedBlocks: [], template: [], templateLock: null } },
    ]);

    assert.equal(typeof registered['test/section'].save, 'function');
    assert.equal(registered['test/section'].save().type, InnerBlocks.Content);
});

test('the server rendered preview is wrapped in Disabled so it cannot be clicked or tabbed into', () => {
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }]);
    const { elements } = edit(registered['test/hero'], {});

    const preview = elements.find((element) => element.type === 'ServerSideRender');
    const disabled = elements.find((element) => element.type === 'Disabled');

    assert.ok(disabled, 'the preview must be rendered inside Disabled');
    assert.ok(flatten(disabled.children).includes(preview));
});

test('a container renders InnerBlocks with the payload props and no preview', () => {
    const { registered, InnerBlocks } = register([
        {
            name: 'test/section',
            attributes: {},
            innerBlocks: {
                allowedBlocks: ['core/heading'],
                template: [['core/heading', { level: 2 }]],
                templateLock: 'insert',
            },
        },
    ]);
    const { elements } = edit(registered['test/section'], {});

    const inner = elements.find((element) => element.type === InnerBlocks);

    assert.ok(!elements.some((element) => element.type === 'ServerSideRender'));
    assert.deepEqual(inner.props, {
        allowedBlocks: ['core/heading'],
        template: [['core/heading', { level: 2 }]],
        templateLock: 'insert',
    });
});

test('the block wrapper spreads useBlockProps, as apiVersion 3 requires', () => {
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }]);
    const { elements } = edit(registered['test/hero'], {});

    const wrapper = elements.find((element) => element.type === 'div');

    assert.equal(wrapper.props.className, 'wp-block');
});

test('an integer attribute is truncated instead of storing a float WordPress rejects', () => {
    const { registered } = register([
        { name: 'test/hero', attributes: { level: field('number', 'integer') }, innerBlocks: null },
    ]);
    const { elements, changes } = edit(registered['test/hero'], { level: 2 });

    const control = elements.find((element) => element.type === 'TextControl');

    assert.equal(control.props.step, 1);

    control.props.onChange('2.7');
    control.props.onChange('');

    assert.deepEqual(changes, [{ level: 2 }, { level: undefined }]);
});

test('a number attribute keeps its decimals', () => {
    const { registered } = register([
        { name: 'test/hero', attributes: { ratio: field('number', 'number') }, innerBlocks: null },
    ]);
    const { elements, changes } = edit(registered['test/hero'], { ratio: 1 });

    const control = elements.find((element) => element.type === 'TextControl');

    assert.equal(control.props.step, undefined);

    control.props.onChange('2.5');

    assert.deepEqual(changes, [{ ratio: 2.5 }]);
});

test('a select writes back the value the schema declared, not the stringified one', () => {
    const { registered } = register([
        {
            name: 'test/hero',
            attributes: {
                level: field('select', 'integer', {
                    options: [
                        { label: 'H2', value: 2 },
                        { label: 'H3', value: 3 },
                    ],
                }),
            },
            innerBlocks: null,
        },
    ]);
    const { elements, changes } = edit(registered['test/hero'], { level: 2 });

    const control = elements.find((element) => element.type === 'SelectControl');

    assert.deepEqual(control.props.options, [
        { label: 'H2', value: '2' },
        { label: 'H3', value: '3' },
    ]);

    control.props.onChange('3');

    assert.deepEqual(changes, [{ level: 3 }]);
});

test('an attribute with no supported control is skipped without a sidebar', () => {
    const { registered } = register([
        { name: 'test/hero', attributes: { meta: field(null, 'object') }, innerBlocks: null },
    ]);
    const { elements } = edit(registered['test/hero'], {});

    assert.ok(!elements.some((element) => element.type === 'InspectorControls'));
});

test('one malformed definition does not stop the others from registering', () => {
    const { registered } = register([
        null,
        { name: 42 },
        { name: 'test/hero', attributes: {}, innerBlocks: null },
    ]);

    assert.deepEqual(Object.keys(registered), ['test/hero']);
});

test('a missing payload registers nothing at all', () => {
    const { registered } = register(undefined);

    assert.deepEqual(Object.keys(registered), []);
});

test('a gallery stores several ids and asks the picker for a multiple selection', () => {
    const { registered } = register([
        {
            name: 'test/gallery',
            attributes: { images: field('gallery', 'array') },
            innerBlocks: null,
        },
    ]);

    const { elements, changes } = edit(registered['test/gallery'], { images: [] });
    const control = elements.find((element) => typeof element.type === 'function');
    const media = find(renderComponent(control), 'MediaUpload');

    assert.equal(media.props.multiple, true);
    // Unqualified, a gallery is a gallery of images.
    assert.deepEqual(media.props.allowedTypes, ['image']);

    media.props.onSelect([{ id: 7 }, { id: 9 }, { nope: true }]);

    assert.deepEqual(changes, [{ images: [7, 9] }]);
});

test('a gallery names its selection while the records are still loading', () => {
    const { registered } = register([
        { name: 'test/gallery', attributes: { images: field('gallery', 'array') }, innerBlocks: null },
    ]);

    const { elements } = edit(registered['test/gallery'], { images: [4, 5] });
    const control = elements.find((element) => typeof element.type === 'function');
    const rendered = renderComponent(control);
    const paragraph = find(rendered, 'p');

    assert.equal(paragraph.children[0], '2 items selected');
});

test('a file control offers the media types the schema asked for, not just images', () => {
    const { registered } = register(
        [
            {
                name: 'test/audio',
                attributes: { son: field('file', 'integer', { allowedTypes: ['audio'] }) },
                innerBlocks: null,
            },
        ],
        { records: { attachment: [{ id: 12, title: { rendered: 'Repet-fanfare.mp3' }, slug: 'repet' }] } },
    );

    const { elements, changes } = edit(registered['test/audio'], { son: 12 });
    const control = elements.find((element) => typeof element.type === 'function');
    const rendered = renderComponent(control);
    const media = find(rendered, 'MediaUpload');

    assert.deepEqual(media.props.allowedTypes, ['audio']);
    assert.equal(media.props.value, 12);
    // The point of the control: the author reads a filename, not an id.
    assert.equal(find(rendered, 'p').children[0], 'Repet-fanfare.mp3');

    media.props.onSelect({ id: 30 });

    assert.deepEqual(changes, [{ son: 30 }]);
});

test('a posts control keeps the author order and appends rather than replaces', () => {
    const { registered } = register(
        [
            {
                name: 'test/relations',
                attributes: { relations: field('posts', 'array', { postTypes: ['termes'] }) },
                innerBlocks: null,
            },
        ],
        {
            records: {
                termes: [
                    { id: 3, title: { rendered: 'Zinneke' }, type: 'termes' },
                    { id: 8, title: { rendered: 'Rara' }, type: 'termes' },
                    { id: 9, title: { rendered: 'Kompa' }, type: 'termes' },
                ],
            },
        },
    );

    const { elements, changes } = edit(registered['test/relations'], { relations: [8, 3] });
    const control = elements.find((element) => typeof element.type === 'function');
    const rendered = renderComponent(control);

    // Author order, not the order the store returned them in.
    const labels = rendered.filter((element) => element.type === 'span').map((element) => element.children[0]);

    assert.deepEqual(labels, ['Rara', 'Zinneke']);

    const combobox = find(rendered, 'ComboboxControl');

    // Already chosen posts are not offered twice.
    assert.deepEqual(
        combobox.props.options.map((option) => option.value),
        [9],
    );

    combobox.props.onChange(9);

    assert.deepEqual(changes, [{ relations: [8, 3, 9] }]);
});

test('a posts control ignores a selection it already holds', () => {
    const { registered } = register(
        [
            {
                name: 'test/relations',
                attributes: { relations: field('posts', 'array', { postTypes: ['termes'] }) },
                innerBlocks: null,
            },
        ],
        { records: { termes: [{ id: 3, title: { rendered: 'Zinneke' }, type: 'termes' }] } },
    );

    const { elements, changes } = edit(registered['test/relations'], { relations: [3] });
    const control = elements.find((element) => typeof element.type === 'function');
    const combobox = find(renderComponent(control), 'ComboboxControl');

    combobox.props.onChange(3);

    assert.deepEqual(changes, []);
});
