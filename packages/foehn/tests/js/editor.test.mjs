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
function register(definitions) {
    const registered = {};

    const InnerBlocks = function InnerBlocks() {};
    InnerBlocks.Content = function Content() {};

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
