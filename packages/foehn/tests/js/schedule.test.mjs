/**
 * Tests for the "Schedule" panel of the block inspector.
 *
 * Run with `npm run test:editor`.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const SOURCE = readFileSync(new URL('../../resources/js/schedule.js', import.meta.url), 'utf8');

function createElement(type, props, ...children) {
    return { type, props: props || {}, children };
}

/**
 * Load the script against a fake `window.wp` and return the filters it added.
 */
function load() {
    const filters = {};
    const wp = {
        hooks: {
            addFilter(hook, namespace, callback) {
                filters[hook] = callback;
            },
        },
        compose: {
            createHigherOrderComponent(factory) {
                return factory;
            },
        },
        element: { createElement, Fragment: 'Fragment' },
        blockEditor: { InspectorControls: 'InspectorControls' },
        components: { PanelBody: 'PanelBody', TextControl: 'TextControl' },
    };

    vm.runInNewContext(SOURCE, { window: { wp } });

    return filters;
}

function findAll(node, type, found = []) {
    if (!node || typeof node !== 'object') {
        return found;
    }

    if (node.type === type) {
        found.push(node);
    }

    (node.children || []).forEach((child) => findAll(child, type, found));

    return found;
}

test('adds the two date attributes and keeps the others', () => {
    const settings = load()['blocks.registerBlockType']({ attributes: { content: { type: 'string' } } });

    assert.deepEqual(JSON.parse(JSON.stringify(settings.attributes)), {
        content: { type: 'string' },
        visibleFrom: { type: 'string' },
        visibleUntil: { type: 'string' },
    });
});

test('adds a Schedule panel with two datetime-local fields to a selected block', () => {
    const Edit = load()['editor.BlockEdit']('BlockEdit');
    const updates = [];
    const tree = Edit({
        isSelected: true,
        attributes: { visibleFrom: '2026-12-01T09:00' },
        setAttributes: (update) => updates.push(update),
    });

    const panel = findAll(tree, 'PanelBody')[0];
    assert.equal(panel.props.title, 'Schedule');
    assert.equal(panel.props.initialOpen, true);

    const [from, until] = findAll(tree, 'TextControl');
    assert.equal(from.props.type, 'datetime-local');
    assert.equal(from.props.value, '2026-12-01T09:00');
    assert.equal(until.props.value, '');

    until.props.onChange('2026-12-24T18:00');
    from.props.onChange('');
    // Objects built in the script's own realm: compared by value, not by prototype.
    assert.equal(updates.length, 2);
    assert.equal(updates[0].visibleUntil, '2026-12-24T18:00');
    assert.ok('visibleFrom' in updates[1]);
    assert.equal(updates[1].visibleFrom, undefined);
});

test('renders the block alone when it is not selected', () => {
    const Edit = load()['editor.BlockEdit']('BlockEdit');
    const tree = Edit({ isSelected: false, attributes: {}, setAttributes() {} });

    assert.equal(tree.type, 'BlockEdit');
});

test('does nothing outside the block editor', () => {
    assert.doesNotThrow(() => vm.runInNewContext(SOURCE, { window: {} }));
});
