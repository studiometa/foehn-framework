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
function register(definitions, entities = {}, canvas = {}) {
    const registered = {};
    const effects = [];
    const requests = [];

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
            cloneElement(element, props, ...children) {
                return {
                    ...element,
                    props: { ...element.props, ...props },
                    children: children.length > 0 ? children : element.children,
                };
            },
            Fragment: 'Fragment',
            // One render per call, so the setter only has to exist: nothing in these
            // tests depends on a state change surviving to a second render.
            useState(initial) {
                // The canvas render state: a test hands over the server render it wants
                // the block drawn from, as if the request had already come back.
                if (initial && typeof initial === 'object' && 'html' in initial) {
                    return [{ html: canvas.html ?? null, error: canvas.error ?? null }, () => {}];
                }

                return [initial, () => {}];
            },
            useEffect(effect) {
                effects.push(effect);
            },
            useMemo(factory) {
                return factory();
            },
            useRef(initial) {
                return { current: initial };
            },
        },
        apiFetch(request) {
            requests.push(request);

            return new Promise(() => {});
        },
        data: {
            useSelect(callback) {
                return callback(() => store);
            },
        },
        blockEditor: {
            BlockControls: 'BlockControls',
            InspectorControls: 'InspectorControls',
            InnerBlocks,
            MediaReplaceFlow: 'MediaReplaceFlow',
            MediaUpload: 'MediaUpload',
            MediaUploadCheck: 'MediaUploadCheck',
            RichText: 'RichText',
            useBlockProps: () => ({ className: 'wp-block', 'data-block': 'client-id' }),
            useInnerBlocksProps: (props, innerBlocksProps) => ({ ...props, innerBlocksProps }),
        },
        components: {
            BaseControl: 'BaseControl',
            Button: 'Button',
            PanelBody: 'PanelBody',
            Spinner: 'Spinner',
            ComboboxControl: 'ComboboxControl',
            SelectControl: 'SelectControl',
            TextControl: 'TextControl',
            TextareaControl: 'TextareaControl',
            ToggleControl: 'ToggleControl',
        },
    };

    // The registrar parses the server render with DOMParser. Node has none, so the
    // test hands over the parsed document itself; any other string is read as
    // text, which is what the plain text conversion of an in-place field needs.
    class DOMParser {
        parseFromString(html) {
            if (canvas.document && html === canvas.html) {
                return canvas.document;
            }

            return { body: { textContent: String(html).replace(/<[^>]+>/g, '') } };
        }
    }

    // Run in this context rather than a fresh one: a new context has its own
    // intrinsics, and every object the registrar builds would then fail a strict
    // deep comparison against a host object for reasons that say nothing about the code.
    globalThis.window = { wp, foehnBlocks: definitions, console, DOMParser };

    try {
        vm.runInThisContext(SOURCE);
    } finally {
        delete globalThis.window;
    }

    return { registered, InnerBlocks, effects, requests };
}

const SVG = 'http://www.w3.org/2000/svg';

/**
 * A DOM element, as DOMParser would hand it over: enough of the interface for the
 * registrar to walk it.
 */
function h(tag, attributes = {}, ...children) {
    return {
        nodeType: 1,
        localName: tag,
        namespaceURI: tag === 'svg' || tag === 'path' ? SVG : 'http://www.w3.org/1999/xhtml',
        attributes: Object.entries(attributes).map(([name, value]) => ({ name, value })),
        childNodes: children.map(toNode),
        getAttribute: (name) => (name in attributes ? attributes[name] : null),
        hasAttribute: (name) => name in attributes,
    };
}

function toNode(child) {
    return typeof child === 'string' ? { nodeType: 3, nodeValue: child } : child;
}

/**
 * A parsed server render, and the HTML string the registrar is told it came from.
 */
let renders = 0;

function render(...children) {
    renders++;

    return {
        html: '<render ' + renders + '>',
        document: { body: { childNodes: children.map(toNode) } },
    };
}

const CONTAINER = { allowedBlocks: ['core/paragraph'], template: [], templateLock: null };

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

test('until the first render comes back, the block shows a spinner in its wrapper', () => {
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }]);
    const { elements } = edit(registered['test/hero'], {});

    const wrapper = elements.find((element) => element.type === 'div');

    assert.equal(wrapper.props.className, 'wp-block');
    assert.ok(find(elements, 'Spinner'));
});

test('the canvas asks for the canvas render of the block, with its attributes', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });

    const { registered, effects, requests } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }]);

    edit(registered['test/hero'], { title: 'Hello' });
    effects.forEach((effect) => effect());
    t.mock.timers.tick(0);

    assert.equal(requests.length, 1);
    assert.equal(requests[0].method, 'POST');
    assert.match(requests[0].path, /^\/wp\/v2\/block-renderer\/test\/hero\?context=edit&foehn_canvas=1/);
    assert.deepEqual(requests[0].data, { attributes: { title: 'Hello' } });
});

test('the root element of the render becomes the block element, with no wrapper around it', () => {
    const canvas = render('\n', h('section', { class: 'hero', id: 'top' }, 'Hi'), '\n');
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }], {}, canvas);
    const { elements } = edit(registered['test/hero'], {});

    const section = find(elements, 'section');

    assert.ok(!elements.some((element) => element.type === 'div'), 'no wrapper div');
    assert.equal(section.props.className, 'hero wp-block');
    assert.equal(section.props.id, 'top');
    assert.equal(section.props['data-block'], 'client-id');
    assert.deepEqual(section.children, ['Hi']);
});

test('a render with several root nodes is wrapped once, in the block element', () => {
    const canvas = render(h('h2', {}, 'A'), h('p', {}, 'B'));
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }], {}, canvas);
    const { elements } = edit(registered['test/hero'], {});

    const wrapper = find(elements, 'div');

    assert.equal(wrapper.props.className, 'wp-block');
    assert.deepEqual(
        wrapper.children.map((child) => child.type),
        ['h2', 'p'],
    );
});

test('the inner blocks are mounted on the element that holds the marker, as its direct children', () => {
    const canvas = render(h('section', {}, h('div', { class: 'richtext' }, ' ', h('foehn-inner-blocks'), ' ')));
    const { registered } = register([{ name: 'test/section', attributes: {}, innerBlocks: CONTAINER }], {}, canvas);
    const { elements } = edit(registered['test/section'], {});

    const host = elements.find((element) => typeof element.type === 'function' && element.props.tagName === 'div');

    assert.ok(host, 'the marker parent hosts the inner blocks');
    assert.equal(host.props.elementProps.className, 'richtext');
    assert.deepEqual(host.props.innerBlocksProps, { allowedBlocks: ['core/paragraph'] });

    const hosted = host.type(host.props);

    assert.equal(hosted.type, 'div');
    assert.deepEqual(hosted.props.innerBlocksProps, { allowedBlocks: ['core/paragraph'] });
});

test('a marker that shares its parent with other content becomes an InnerBlocks element', () => {
    const canvas = render(h('section', {}, h('h2', {}, 'Title'), h('foehn-inner-blocks')));
    const { registered, InnerBlocks } = register(
        [{ name: 'test/section', attributes: {}, innerBlocks: CONTAINER }],
        {},
        canvas,
    );
    const { elements } = edit(registered['test/section'], {});

    assert.ok(find(elements, InnerBlocks));
});

test('a container whose template never prints content still shows its inner blocks', (t) => {
    t.mock.method(console, 'warn', () => {});

    const canvas = render(h('section', {}, h('h2', {}, 'Title')));
    const { registered, InnerBlocks } = register(
        [{ name: 'test/section', attributes: {}, innerBlocks: CONTAINER }],
        {},
        canvas,
    );
    const { elements } = edit(registered['test/section'], {});

    assert.ok(find(elements, InnerBlocks));
    assert.equal(console.warn.mock.callCount(), 1);
});

test('a template that prints content twice mounts the inner blocks at the first place only', (t) => {
    t.mock.method(console, 'warn', () => {});

    const canvas = render(
        h('section', {}, h('div', { class: 'a' }, h('foehn-inner-blocks')), h('div', { class: 'b' }, h('foehn-inner-blocks'))),
    );
    const { registered, InnerBlocks } = register(
        [{ name: 'test/section', attributes: {}, innerBlocks: CONTAINER }],
        {},
        canvas,
    );
    const { elements } = edit(registered['test/section'], {});

    const hosts = elements.filter((element) => typeof element.type === 'function' && element.props.tagName === 'div');
    const second = find(elements, 'div');

    assert.equal(hosts.length, 1);
    assert.equal(hosts[0].props.elementProps.className, 'a');
    assert.ok(!find(elements, InnerBlocks), 'no second InnerBlocks');
    assert.equal(second.props.className, 'b');
    assert.deepEqual(second.children, [null]);
    assert.equal(console.warn.mock.callCount(), 1);
});

test('a container whose render failed still shows and saves its inner blocks', () => {
    const { registered } = register([{ name: 'test/section', attributes: {}, innerBlocks: CONTAINER }], {}, {
        error: 'Nope',
    });
    const { elements } = edit(registered['test/section'], {});

    const host = elements.find((element) => typeof element.type === 'function' && element.props.tagName === 'div');

    assert.ok(host);
    assert.equal(host.props.elementProps.className, 'wp-block');
});

test('a marked element becomes a plain text RichText bound to its attribute', () => {
    const canvas = render(h('section', {}, h('h2', { 'data-foehn-editable': 'title', class: 'big' }, 'old')));
    const { registered } = register(
        [{ name: 'test/hero', attributes: { title: field('text', 'string', { placeholder: 'Type' }) }, innerBlocks: null }],
        {},
        canvas,
    );
    const { elements, changes } = edit(registered['test/hero'], { title: 'Fish & <chips>' });

    const editable = find(elements, 'RichText');

    assert.equal(editable.props.tagName, 'h2');
    assert.equal(editable.props.className, 'big');
    assert.equal(editable.props.value, 'Fish &amp; &lt;chips&gt;');
    assert.equal(editable.props.placeholder, 'Type');
    assert.deepEqual(editable.props.allowedFormats, []);
    assert.equal(editable.props.disableLineBreaks, true);
    assert.equal(editable.props['data-foehn-editable'], undefined);

    editable.props.onChange('Fish <b>and</b> chips');

    assert.deepEqual(changes, [{ title: 'Fish and chips' }]);
});

test('a marked root element is the block element and the RichText, with no wrapper', () => {
    const canvas = render(h('h2', { 'data-foehn-editable': 'title', class: 'big' }, 'old'));
    const { registered } = register(
        [{ name: 'test/heading', attributes: { title: field('text', 'string') }, innerBlocks: null }],
        {},
        canvas,
    );
    const { elements } = edit(registered['test/heading'], { title: 'Hi' });

    const editable = find(elements, 'RichText');

    assert.ok(!elements.some((element) => element.type === 'div'), 'no wrapper div');
    assert.equal(editable.props.tagName, 'h2');
    assert.equal(editable.props.className, 'big wp-block');
    assert.equal(editable.props['data-block'], 'client-id');
    assert.equal(editable.props.value, 'Hi');
});

test('a richtext attribute keeps its formatting as HTML', () => {
    const canvas = render(h('figure', {}, h('figcaption', { 'data-foehn-editable': 'caption' })));
    const { registered } = register(
        [{ name: 'test/image', attributes: { caption: field('richtext', 'string') }, innerBlocks: null }],
        {},
        canvas,
    );
    const { elements, changes } = edit(registered['test/image'], { caption: 'A <strong>b</strong>' });

    const editable = find(elements, 'RichText');

    assert.equal(editable.props.value, 'A <strong>b</strong>');
    assert.equal(editable.props.allowedFormats, undefined);

    editable.props.onChange('A <em>c</em>');

    assert.deepEqual(changes, [{ caption: 'A <em>c</em>' }]);
});

test('an attribute edited on the canvas has no sidebar field; an unmarked one keeps it', () => {
    const canvas = render(h('section', {}, h('h2', { 'data-foehn-editable': 'title' })));
    const { registered } = register(
        [
            {
                name: 'test/hero',
                attributes: { title: field('text', 'string'), caption: field('richtext', 'string') },
                innerBlocks: null,
            },
        ],
        {},
        canvas,
    );
    const { elements } = edit(registered['test/hero'], {});

    assert.ok(!find(elements, 'TextControl'), 'title is edited in place');
    assert.ok(find(elements, 'TextareaControl'), 'caption falls back to the sidebar');
});

test('the render is drawn inert: links stay, handlers and scripts go', () => {
    const canvas = render(
        h(
            'div',
            { onclick: 'alert(1)', style: "background: url('data:image/png;base64,AA;BB') ; --gap: 2px; margin-top: 1px" },
            h('a', { href: '/somewhere', tabindex: '0' }, 'Go'),
            h('script', {}, 'alert(1)'),
            h('svg', { viewBox: '0 0 24 24', 'stroke-width': '2' }, h('path', { d: 'M0' })),
            h('video', { controls: '', playsinline: '' }),
        ),
    );
    const { registered } = register([{ name: 'test/hero', attributes: {}, innerBlocks: null }], {}, canvas);
    const { elements } = edit(registered['test/hero'], {});

    const root = find(elements, 'div');
    const link = find(elements, 'a');
    const svg = find(elements, 'svg');
    const video = find(elements, 'video');

    assert.equal(root.props.onclick, undefined);
    assert.deepEqual(root.props.style, {
        background: "url('data:image/png;base64,AA;BB')",
        '--gap': '2px',
        marginTop: '1px',
    });
    assert.ok(!find(elements, 'script'));
    assert.equal(link.props.href, '/somewhere');
    assert.equal(link.props.tabIndex, '0');

    let prevented = false;

    link.props.onClick({ preventDefault: () => (prevented = true) });
    assert.ok(prevented, 'a link cannot leave the editor');

    assert.equal(svg.props.viewBox, '0 0 24 24');
    assert.equal(svg.props.strokeWidth, '2');
    assert.equal(video.props.controls, true);
    assert.equal(video.props.playsInline, true);
});

test('an image attribute gets a replace button in the block toolbar', () => {
    const { registered } = register([
        { name: 'test/hero', attributes: { imageId: field('image', 'integer', { label: 'Background' }) }, innerBlocks: null },
    ]);
    const { elements, changes } = edit(registered['test/hero'], { imageId: 12 });

    const toolbar = find(elements, 'BlockControls');
    const replace = find(elements, 'MediaReplaceFlow');

    assert.equal(toolbar.props.group, 'other');
    assert.equal(replace.props.mediaId, 12);
    assert.equal(replace.props.name, 'Replace background');

    replace.props.onSelect({ id: 34 });

    assert.deepEqual(changes, [{ imageId: 34 }]);
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
