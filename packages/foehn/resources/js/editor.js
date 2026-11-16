/**
 * Føhn — generic block editor registrar.
 *
 * Attaches editor behaviour to every block Føhn discovered, from a payload printed
 * by the PHP side as `window.foehnBlocks`.
 *
 * This file contains no project specific and no block specific knowledge on purpose.
 * The same bytes ship with every Føhn install, so the file can be copied into the web
 * root once, at `composer install` time, and never regenerated when a developer adds
 * a block. Deriving a smaller script from the discovered blocks would tie the static
 * half of the editor layer to the dynamic half, which is the one thing to avoid.
 *
 * There is no build step: WordPress already exposes React, the editor and the
 * component library as globals, so this is plain ES2017 reading `window.wp`.
 */
(function (wp) {
    'use strict';

    // Outside the block editor, or before the payload is printed, do nothing at all.
    if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
        return;
    }

    const definitions = window.foehnBlocks;

    if (!Array.isArray(definitions)) {
        return;
    }

    const el = wp.element.createElement;
    const cloneElement = wp.element.cloneElement;
    const Fragment = wp.element.Fragment;
    const useEffect = wp.element.useEffect;
    const useMemo = wp.element.useMemo;
    const useRef = wp.element.useRef;
    const useState = wp.element.useState;
    const registerBlockType = wp.blocks.registerBlockType;
    const BlockControls = wp.blockEditor.BlockControls;
    const InspectorControls = wp.blockEditor.InspectorControls;
    const InnerBlocks = wp.blockEditor.InnerBlocks;
    const MediaReplaceFlow = wp.blockEditor.MediaReplaceFlow;
    const MediaUpload = wp.blockEditor.MediaUpload;
    const MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
    const RichText = wp.blockEditor.RichText;
    const useBlockProps = wp.blockEditor.useBlockProps;
    const useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
    const BaseControl = wp.components.BaseControl;
    const Button = wp.components.Button;
    const ComboboxControl = wp.components.ComboboxControl;
    const PanelBody = wp.components.PanelBody;
    const SelectControl = wp.components.SelectControl;
    const Spinner = wp.components.Spinner;
    const TextControl = wp.components.TextControl;
    const TextareaControl = wp.components.TextareaControl;
    const ToggleControl = wp.components.ToggleControl;

    // The `file` and `posts` controls name what they hold — a filename, a post
    // title — which means reading entity records the editor has already loaded.
    // `wp.data` ships with the editor; guarding anyway keeps this file harmless
    // wherever it is loaded.
    const useSelect = wp.data && wp.data.useSelect;
    const apiFetch = wp.apiFetch;
    const DOMParser = window.DOMParser;
    const logger = window.console;

    // Must match the constants of `Studiometa\Foehn\Blocks\EditorCanvas`.
    const CANVAS_PARAM = 'foehn_canvas';
    const INNER_BLOCKS_TAG = 'foehn-inner-blocks';
    const EDITABLE_ATTRIBUTE = 'data-foehn-editable';

    // How long the canvas waits after the last attribute change before it asks the
    // server for a new render. Long enough to not render once per keystroke in a
    // sidebar field, short enough to feel live.
    const RENDER_DELAY = 300;

    /**
     * Coerce a value for a text input, which cannot hold null or undefined.
     *
     * @param {*} value Current attribute value.
     * @return {string} Value the input can display.
     */
    function toInputValue(value) {
        return value === undefined || value === null ? '' : String(value);
    }

    /**
     * Coerce a text input value back to a number.
     *
     * An emptied field resolves to `undefined` so WordPress falls back to the attribute
     * default instead of storing `NaN` in the post content.
     *
     * @param {string}  value   Raw input value.
     * @param {boolean} integer Whether the attribute is declared as an integer.
     * @return {number|undefined} Numeric value, or undefined when there is none.
     */
    function toNumber(value, integer) {
        if (value === '' || value === null || value === undefined) {
            return undefined;
        }

        const number = Number(value);

        if (Number.isNaN(number)) {
            return undefined;
        }

        return integer ? Math.trunc(number) : number;
    }

    /**
     * Control renderers keyed by the control name resolved on the PHP side by
     * `Studiometa\Foehn\Blocks\BlockAttributeSchema::toEditorFields()`.
     *
     * Each renderer receives the editor field descriptor, the current attribute value and
     * a setter for that single attribute. The `__next*` props opt into the current
     * @wordpress/components layout and sizing so the editor console stays free of
     * deprecation notices; they are only passed to the controls that accept them.
     */
    const CONTROLS = {
        text(field, value, setValue) {
            return el(TextControl, {
                __nextHasNoMarginBottom: true,
                __next40pxDefaultSize: true,
                label: field.label,
                help: field.help || undefined,
                value: toInputValue(value),
                onChange: setValue,
            });
        },

        textarea(field, value, setValue) {
            // TextareaControl spreads unknown props onto the <textarea>, and it has no
            // 40px size variant, so it only gets the margin opt-in.
            return el(TextareaControl, {
                __nextHasNoMarginBottom: true,
                label: field.label,
                help: field.help || undefined,
                value: toInputValue(value),
                onChange: setValue,
            });
        },

        // The sidebar fallback of a `richtext` attribute, for a template that does
        // not mark it with block_editable(). The value stays HTML.
        richtext(field, value, setValue) {
            return CONTROLS.textarea(field, value, setValue);
        },

        toggle(field, value, setValue) {
            return el(ToggleControl, {
                __nextHasNoMarginBottom: true,
                label: field.label,
                help: field.help || undefined,
                checked: !!value,
                onChange: setValue,
            });
        },

        number(field, value, setValue) {
            // An `integer` attribute must never be given a float: WordPress validates
            // the value against the schema in the block renderer endpoint and again in
            // WP_Block_Type::prepare_attributes_for_render(), and a rejected value is
            // replaced by the default — the editor and the front end would disagree.
            const integer = field.type === 'integer';

            // Deliberately a TextControl with type="number" rather than
            // wp.components.__experimentalNumberControl, whose global name is unstable
            // across WordPress versions and would break the editor on an upgrade.
            return el(TextControl, {
                __nextHasNoMarginBottom: true,
                __next40pxDefaultSize: true,
                type: 'number',
                step: integer ? 1 : undefined,
                label: field.label,
                help: field.help || undefined,
                value: toInputValue(value),
                onChange: function (next) {
                    setValue(toNumber(next, integer));
                },
            });
        },

        select(field, value, setValue) {
            const options = Array.isArray(field.options) ? field.options : [];

            return el(SelectControl, {
                __nextHasNoMarginBottom: true,
                __next40pxDefaultSize: true,
                label: field.label,
                help: field.help || undefined,
                value: toInputValue(value),
                options: options.map(function (option) {
                    return { label: String(option.label), value: toInputValue(option.value) };
                }),
                onChange: function (next) {
                    // A <select> only ever hands back a string. Map the choice back onto the
                    // value the schema declared so numeric and boolean enums stay typed.
                    const selected = options.filter(function (option) {
                        return toInputValue(option.value) === next;
                    })[0];

                    setValue(selected ? selected.value : next);
                },
            });
        },

        image(field, value, setValue) {
            if (!MediaUpload || !MediaUploadCheck) {
                return null;
            }

            // MediaUpload renders no label of its own, so BaseControl provides the label
            // and help text the other controls get for free.
            return el(
                BaseControl,
                {
                    __nextHasNoMarginBottom: true,
                    label: field.label,
                    help: field.help || undefined,
                },
                el(
                    MediaUploadCheck,
                    null,
                    el(MediaUpload, {
                        allowedTypes: ['image'],
                        value: value || undefined,
                        onSelect: function (media) {
                            setValue(media && media.id ? media.id : undefined);
                        },
                        render: function (picker) {
                            return renderImageButtons(value, setValue, picker);
                        },
                    }),
                ),
            );
        },

        // The three controls below hold what core's own components do not offer as a
        // single control: several images, one non image attachment, and a list of
        // related posts. Each is a component rather than an inline element tree
        // because each reads entity records, and a hook has to live in a component
        // whose identity does not change with the shape of a block's attributes.
        gallery(field, value, setValue) {
            return el(GalleryControl, { field: field, value: value, setValue: setValue });
        },

        file(field, value, setValue) {
            return el(FileControl, { field: field, value: value, setValue: setValue });
        },

        posts(field, value, setValue) {
            return el(PostsControl, { field: field, value: value, setValue: setValue });
        },
    };

    /**
     * Build the buttons of the image control.
     *
     * Extracted from the control itself only to keep the element tree readable.
     *
     * @param {*}        value    Current attachment id, if any.
     * @param {Function} setValue Setter for the attribute.
     * @param {Object}   picker   Media picker handed over by MediaUpload.
     * @return {Object} Button elements.
     */
    function renderImageButtons(value, setValue, picker) {
        const select = el(
            Button,
            { __next40pxDefaultSize: true, variant: 'secondary', onClick: picker.open },
            value ? 'Replace image' : 'Select image',
        );

        if (!value) {
            return select;
        }

        // Without this the attribute could be changed but never cleared.
        const remove = el(
            Button,
            {
                __next40pxDefaultSize: true,
                variant: 'tertiary',
                isDestructive: true,
                onClick: function () {
                    setValue(undefined);
                },
            },
            'Remove',
        );

        return el(Fragment, null, select, remove);
    }

    /**
     * Coerce an attribute value to a list of numeric ids.
     *
     * Both list controls store ids, and both can be handed a stale or hand written
     * attribute. Anything that is not a usable id is dropped rather than rendered
     * as an empty slot the editor cannot explain.
     *
     * @param {*} value Current attribute value.
     * @return {Array} Numeric ids, possibly empty.
     */
    function toIds(value) {
        if (!Array.isArray(value)) {
            return [];
        }

        return value.map(Number).filter(function (id) {
            return Number.isInteger(id) && id > 0;
        });
    }

    /**
     * Several attachments, in the order the author arranged them.
     *
     * `image` cannot serve here: MediaUpload's `multiple` changes both what
     * `onSelect` receives and what the modal allows, and the value stops being a
     * scalar. Ordering is the reason this stores ids rather than a gallery shortcode
     * — a gallery whose order does not survive a reload is a gallery nobody trusts.
     *
     * @param {Object} props Field descriptor, current value and setter.
     * @return {Object} Control element.
     */
    function GalleryControl(props) {
        const ids = toIds(props.value);
        const setValue = props.setValue;

        // One request for the whole selection. `getMedia` per id would mean a hook
        // count that changes with the selection, which React forbids.
        const items = useSelect
            ? useSelect(
                  function (select) {
                      if (!ids.length) {
                          return [];
                      }

                      return (
                          select('core').getEntityRecords('postType', 'attachment', {
                              include: ids,
                              per_page: ids.length,
                              orderby: 'include',
                          }) || []
                      );
                  },
                  [ids.join(',')],
              )
            : [];

        if (!MediaUpload || !MediaUploadCheck) {
            return null;
        }

        return el(
            BaseControl,
            {
                __nextHasNoMarginBottom: true,
                label: props.field.label,
                help: props.field.help || undefined,
            },
            el('div', { className: 'foehn-gallery-control' }, renderGalleryThumbnails(items, ids)),
            el(
                MediaUploadCheck,
                null,
                el(MediaUpload, {
                    multiple: true,
                    gallery: true,
                    allowedTypes: props.field.allowedTypes || ['image'],
                    value: ids,
                    onSelect: function (media) {
                        setValue(
                            (Array.isArray(media) ? media : [media])
                                .map(function (item) {
                                    return item && item.id;
                                })
                                .filter(Boolean),
                        );
                    },
                    render: function (picker) {
                        return renderGalleryButtons(ids, setValue, picker);
                    },
                }),
            ),
        );
    }

    /**
     * The selection as thumbnails, so the sidebar shows images rather than a count.
     *
     * @param {Array} items Attachment records, once loaded.
     * @param {Array} ids   Selected ids.
     * @return {Object|null} Element, or null while nothing is selected.
     */
    function renderGalleryThumbnails(items, ids) {
        if (!ids.length) {
            return null;
        }

        // Records arrive after the ids do. Showing the count meanwhile beats an empty
        // box that looks like the selection was lost.
        if (!items || !items.length) {
            return el('p', null, ids.length + (ids.length > 1 ? ' items selected' : ' item selected'));
        }

        return el(
            'div',
            {
                style: { display: 'flex', flexWrap: 'wrap', gap: '4px', marginBottom: '8px' },
            },
            items.map(function (item) {
                const source =
                    (item.media_details &&
                        item.media_details.sizes &&
                        item.media_details.sizes.thumbnail &&
                        item.media_details.sizes.thumbnail.source_url) ||
                    item.source_url;

                return el('img', {
                    key: item.id,
                    src: source,
                    alt: item.alt_text || '',
                    width: 48,
                    height: 48,
                    style: { objectFit: 'cover', borderRadius: '2px' },
                });
            }),
        );
    }

    /**
     * Build the buttons of the gallery control.
     *
     * @param {Array}    ids      Selected ids.
     * @param {Function} setValue Setter for the attribute.
     * @param {Object}   picker   Media picker handed over by MediaUpload.
     * @return {Object} Button elements.
     */
    function renderGalleryButtons(ids, setValue, picker) {
        const select = el(
            Button,
            { __next40pxDefaultSize: true, variant: 'secondary', onClick: picker.open },
            ids.length ? 'Edit gallery' : 'Select images',
        );

        if (!ids.length) {
            return select;
        }

        const clear = el(
            Button,
            {
                __next40pxDefaultSize: true,
                variant: 'tertiary',
                isDestructive: true,
                onClick: function () {
                    setValue([]);
                },
            },
            'Clear',
        );

        return el(Fragment, null, select, clear);
    }

    /**
     * One attachment of any kind — an audio file, a PDF, a video.
     *
     * The `image` control hard codes `allowedTypes: ['image']`, which is right for
     * an image and leaves every other attachment unreachable. Here the type comes
     * from the schema, and defaults to no restriction.
     *
     * @param {Object} props Field descriptor, current value and setter.
     * @return {Object} Control element.
     */
    function FileControl(props) {
        const id = Number(props.value) || 0;
        const setValue = props.setValue;

        const attachment = useSelect
            ? useSelect(
                  function (select) {
                      return id ? select('core').getEntityRecord('postType', 'attachment', id) : null;
                  },
                  [id],
              )
            : null;

        if (!MediaUpload || !MediaUploadCheck) {
            return null;
        }

        // The rendered title, or the bare id while the record loads. Naming the file
        // is the whole point of the control: an id tells an author nothing.
        const name = attachment
            ? (attachment.title && attachment.title.rendered) || attachment.slug || String(id)
            : id
              ? 'Attachment ' + id
              : null;

        return el(
            BaseControl,
            {
                __nextHasNoMarginBottom: true,
                label: props.field.label,
                help: props.field.help || undefined,
            },
            name ? el('p', { style: { margin: '0 0 8px' } }, name) : null,
            el(
                MediaUploadCheck,
                null,
                el(MediaUpload, {
                    allowedTypes: props.field.allowedTypes || undefined,
                    value: id || undefined,
                    onSelect: function (media) {
                        setValue(media && media.id ? media.id : undefined);
                    },
                    render: function (picker) {
                        return renderFileButtons(id, setValue, picker);
                    },
                }),
            ),
        );
    }

    /**
     * Build the buttons of the file control.
     *
     * @param {number}   id       Current attachment id, or 0.
     * @param {Function} setValue Setter for the attribute.
     * @param {Object}   picker   Media picker handed over by MediaUpload.
     * @return {Object} Button elements.
     */
    function renderFileButtons(id, setValue, picker) {
        const select = el(
            Button,
            { __next40pxDefaultSize: true, variant: 'secondary', onClick: picker.open },
            id ? 'Replace file' : 'Select file',
        );

        if (!id) {
            return select;
        }

        const remove = el(
            Button,
            {
                __next40pxDefaultSize: true,
                variant: 'tertiary',
                isDestructive: true,
                onClick: function () {
                    setValue(undefined);
                },
            },
            'Remove',
        );

        return el(Fragment, null, select, remove);
    }

    /**
     * A list of related posts, searchable across the post types the schema names.
     *
     * This is the control with no core equivalent, and the reason is ordering: a
     * relation list is authored, not queried, so it stores ids in the order they
     * were added and offers a way to move them. A token field would lose that order
     * and could not tell two posts sharing a title apart.
     *
     * @param {Object} props Field descriptor, current value and setter.
     * @return {Object} Control element.
     */
    function PostsControl(props) {
        const ids = toIds(props.value);
        const setValue = props.setValue;
        const search = useState('');
        const term = search[0];
        const setTerm = search[1];
        const postTypes = props.field.postTypes;

        const data = useSelect
            ? useSelect(
                  function (select) {
                      const core = select('core');
                      // Without an explicit list, every post type the REST API exposes
                      // and that is not built in — which is what "related content"
                      // means when a block does not narrow it.
                      const types =
                          postTypes ||
                          (core.getPostTypes({ per_page: -1 }) || [])
                              .filter(function (type) {
                                  return type.viewable && type.slug !== 'attachment';
                              })
                              .map(function (type) {
                                  return type.slug;
                              });

                      return {
                          // Candidates for the search box.
                          results: types.reduce(function (all, type) {
                              return all.concat(
                                  core.getEntityRecords('postType', type, {
                                      per_page: 20,
                                      search: term || undefined,
                                      _fields: 'id,title,type',
                                  }) || [],
                              );
                          }, []),
                          // The current selection, resolved separately: a chosen post
                          // is usually not in the search results, and it still has to
                          // show its title.
                          chosen: types.reduce(function (all, type) {
                              if (!ids.length) {
                                  return all;
                              }

                              return all.concat(
                                  core.getEntityRecords('postType', type, {
                                      include: ids,
                                      per_page: ids.length,
                                      _fields: 'id,title,type',
                                  }) || [],
                              );
                          }, []),
                      };
                  },
                  [term, ids.join(','), (postTypes || []).join(',')],
              )
            : { results: [], chosen: [] };

        const titles = {};

        data.chosen.concat(data.results).forEach(function (post) {
            titles[post.id] = (post.title && post.title.rendered) || '(untitled)';
        });

        return el(
            BaseControl,
            {
                __nextHasNoMarginBottom: true,
                label: props.field.label,
                help: props.field.help || undefined,
            },
            renderChosenPosts(ids, titles, setValue),
            ComboboxControl
                ? el(ComboboxControl, {
                      __nextHasNoMarginBottom: true,
                      __next40pxDefaultSize: true,
                      label: '',
                      placeholder: 'Search content…',
                      value: null,
                      options: data.results
                          .filter(function (post) {
                              return ids.indexOf(post.id) === -1;
                          })
                          .map(function (post) {
                              return {
                                  label: (post.title && post.title.rendered) || '(untitled)',
                                  value: post.id,
                              };
                          }),
                      onFilterValueChange: function (next) {
                          setTerm(next);
                      },
                      onChange: function (next) {
                          const id = Number(next);

                          if (Number.isInteger(id) && id > 0 && ids.indexOf(id) === -1) {
                              setValue(ids.concat([id]));
                          }
                      },
                  })
                : null,
        );
    }

    /**
     * The chosen posts, in order, each removable and movable.
     *
     * @param {Array}    ids      Selected ids, in author order.
     * @param {Object}   titles   Id to title map, as far as it is resolved.
     * @param {Function} setValue Setter for the attribute.
     * @return {Object|null} Element, or null while nothing is selected.
     */
    function renderChosenPosts(ids, titles, setValue) {
        if (!ids.length) {
            return null;
        }

        function move(index, offset) {
            const next = ids.slice();
            const target = index + offset;

            if (target < 0 || target >= next.length) {
                return;
            }

            const moved = next.splice(index, 1)[0];

            next.splice(target, 0, moved);
            setValue(next);
        }

        return el(
            'ul',
            { style: { margin: '0 0 8px', padding: 0, listStyle: 'none' } },
            ids.map(function (id, index) {
                return el(
                    'li',
                    {
                        key: id,
                        style: { display: 'flex', alignItems: 'center', gap: '4px' },
                    },
                    el('span', { style: { flex: '1 1 auto', fontSize: '12px' } }, titles[id] || 'ID ' + id),
                    el(Button, {
                        size: 'small',
                        icon: 'arrow-up-alt2',
                        label: 'Move up',
                        disabled: index === 0,
                        onClick: function () {
                            move(index, -1);
                        },
                    }),
                    el(Button, {
                        size: 'small',
                        icon: 'arrow-down-alt2',
                        label: 'Move down',
                        disabled: index === ids.length - 1,
                        onClick: function () {
                            move(index, 1);
                        },
                    }),
                    el(Button, {
                        size: 'small',
                        icon: 'no-alt',
                        label: 'Remove',
                        isDestructive: true,
                        onClick: function () {
                            setValue(
                                ids.filter(function (current) {
                                    return current !== id;
                                }),
                            );
                        },
                    }),
                );
            }),
        );
    }

    /**
     * Build the sidebar controls for one block.
     *
     * @param {Object}   definition    Block definition from the payload.
     * @param {Object}   attributes    Current block attributes.
     * @param {Function} setAttributes Attribute setter given to `edit`.
     * @param {Object}   [onCanvas]    Attribute names edited in place on the canvas.
     * @return {Array} Control elements, possibly empty.
     */
    function renderFields(definition, attributes, setAttributes, onCanvas) {
        const fields = definition.attributes || {};

        return Object.keys(fields).reduce(function (elements, key) {
            const field = fields[key] || {};

            // An attribute the author types on the canvas needs no second field.
            if (onCanvas && onCanvas[key]) {
                return elements;
            }
            const render = field.control ? CONTROLS[field.control] : null;

            // An attribute with no supported control is skipped in silence: it still
            // exists server-side, it simply has no sidebar UI.
            if (!render) {
                return elements;
            }

            const element = render(field, attributes[key], function (value) {
                const patch = {};

                patch[key] = value;
                setAttributes(patch);
            });

            if (element) {
                // Block name plus attribute name is unique and stable across renders.
                elements.push(cloneElement(element, { key: definition.name + '/' + key }));
            }

            return elements;
        }, []);
    }

    /**
     * Translate the container configuration into InnerBlocks props, dropping the ones
     * that carry no instruction so InnerBlocks keeps its own defaults.
     *
     * @param {Object} innerBlocks Container configuration from the payload.
     * @return {Object} Props for InnerBlocks.
     */
    function toInnerBlocksProps(innerBlocks) {
        const props = {};

        if (Array.isArray(innerBlocks.allowedBlocks) && innerBlocks.allowedBlocks.length > 0) {
            props.allowedBlocks = innerBlocks.allowedBlocks;
        }

        if (Array.isArray(innerBlocks.template) && innerBlocks.template.length > 0) {
            props.template = innerBlocks.template;
        }

        // `false` is a meaningful lock value (explicitly unlocked), unlike null.
        if (innerBlocks.templateLock !== null && innerBlocks.templateLock !== undefined) {
            props.templateLock = innerBlocks.templateLock;
        }

        return props;
    }

    /*
     * The canvas.
     *
     * The editor draws a Foehn block from the same Twig template as the front end:
     * the server renders it with the `foehn_canvas` flag, and the HTML it returns is
     * turned into React elements here. Two kinds of elements become live editor
     * components on the way:
     *
     * - the inner blocks marker, printed where the template prints `content`,
     *   becomes the block's InnerBlocks — mounted on the marker's parent element
     *   when it is that element's only child, so the inner blocks are direct
     *   children of the template's own element, with no wrapper;
     * - an element marked with `block_editable('<attribute>')` becomes a RichText
     *   bound to that attribute, so the author types where the text is shown.
     *
     * The template's root element receives the block wrapper props, as a core block
     * spreads useBlockProps on its own root: selection, the toolbar and the block
     * supports land on the template's markup instead of on a wrapper around it.
     *
     * The HTML is never inserted with innerHTML: it goes through React, so nothing in
     * it runs, and the editor components inside it keep their state across renders.
     */

    // Elements with no children in HTML.
    const VOID_ELEMENTS = {
        area: true,
        base: true,
        br: true,
        col: true,
        embed: true,
        hr: true,
        img: true,
        input: true,
        link: true,
        meta: true,
        source: true,
        track: true,
        wbr: true,
    };

    // Elements dropped from the canvas: nothing in a server render runs in the editor.
    const DROPPED_ELEMENTS = { script: true, noscript: true, template: true };

    // HTML attribute names whose React prop name differs by more than the case.
    const PROP_NAMES = {
        class: 'className',
        for: 'htmlFor',
        accesskey: 'accessKey',
        allowfullscreen: 'allowFullScreen',
        autocomplete: 'autoComplete',
        autofocus: 'autoFocus',
        autoplay: 'autoPlay',
        colspan: 'colSpan',
        contenteditable: 'contentEditable',
        crossorigin: 'crossOrigin',
        datetime: 'dateTime',
        enctype: 'encType',
        frameborder: 'frameBorder',
        inputmode: 'inputMode',
        maxlength: 'maxLength',
        minlength: 'minLength',
        novalidate: 'noValidate',
        playsinline: 'playsInline',
        readonly: 'readOnly',
        referrerpolicy: 'referrerPolicy',
        rowspan: 'rowSpan',
        spellcheck: 'spellCheck',
        srcdoc: 'srcDoc',
        srclang: 'srcLang',
        srcset: 'srcSet',
        tabindex: 'tabIndex',
        usemap: 'useMap',
        'xlink:href': 'xlinkHref',
        'xml:lang': 'xmlLang',
        'xml:space': 'xmlSpace',
    };

    // Boolean attributes: present means true, whatever the value.
    const BOOLEAN_PROPS = {
        allowFullScreen: true,
        async: true,
        autoFocus: true,
        autoPlay: true,
        checked: true,
        controls: true,
        default: true,
        defer: true,
        disabled: true,
        hidden: true,
        inert: true,
        loop: true,
        multiple: true,
        muted: true,
        noValidate: true,
        open: true,
        playsInline: true,
        readOnly: true,
        required: true,
        reversed: true,
        selected: true,
    };

    const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /**
     * Turn a kebab-case name into camelCase.
     *
     * @param {string} name Name such as `stroke-width` or `-webkit-line-clamp`.
     * @return {string} Name such as `strokeWidth` or `WebkitLineClamp`.
     */
    function camelCase(name) {
        return name.replace(/^-ms-/, 'ms-').replace(/-([a-z])/g, function (match, letter) {
            return letter.toUpperCase();
        });
    }

    /**
     * Parse an inline `style` attribute into a React style object.
     *
     * Declarations are split on the semicolons outside parentheses, so a data URI
     * in a `url()` survives. Custom properties keep their name as is.
     *
     * @param {string} css Value of the style attribute.
     * @return {Object} Style object.
     */
    function parseStyle(css) {
        const style = {};
        const declarations = [];
        let depth = 0;
        let current = '';

        for (let index = 0; index < css.length; index++) {
            const character = css[index];

            if (character === '(') {
                depth++;
            } else if (character === ')') {
                depth = Math.max(0, depth - 1);
            }

            if (character === ';' && depth === 0) {
                declarations.push(current);
                current = '';
            } else {
                current += character;
            }
        }

        declarations.push(current);

        declarations.forEach(function (declaration) {
            const colon = declaration.indexOf(':');

            if (colon === -1) {
                return;
            }

            const property = declaration.slice(0, colon).trim();
            const value = declaration.slice(colon + 1).trim();

            if (property === '' || value === '') {
                return;
            }

            style[property.indexOf('--') === 0 ? property : camelCase(property.toLowerCase())] = value;
        });

        return style;
    }

    /**
     * Stop a click from leaving the editor.
     *
     * @param {Event} event Click or submit event.
     */
    function preventDefault(event) {
        event.preventDefault();
    }

    /**
     * Read the attributes of a DOM element as React props.
     *
     * Event handler attributes are dropped: they would be strings React refuses, and
     * nothing from a server render should run in the editor anyway.
     *
     * @param {Element} node DOM element.
     * @return {Object} Props.
     */
    function toProps(node) {
        const props = {};
        const svg = node.namespaceURI === SVG_NAMESPACE;
        const attributes = node.attributes || [];

        for (let index = 0; index < attributes.length; index++) {
            const name = attributes[index].name;
            const value = attributes[index].value;
            const lower = name.toLowerCase();

            if (lower.indexOf('on') === 0 || lower === EDITABLE_ATTRIBUTE) {
                continue;
            }

            if (lower === 'style') {
                props.style = parseStyle(value);
                continue;
            }

            let prop = PROP_NAMES[lower];

            if (!prop) {
                // `data-*` and `aria-*` stay as they are in HTML and in SVG; the other
                // dashed SVG presentation attributes are camelCase props in React.
                const dashed = lower.indexOf('data-') !== 0 && lower.indexOf('aria-') !== 0;

                prop = svg && dashed ? camelCase(name) : name;
            }

            props[prop] = BOOLEAN_PROPS[prop] ? true : value;
        }

        const tag = node.localName;

        // A form value in the markup is the initial value, not a controlled one.
        if (tag === 'input' || tag === 'textarea' || tag === 'select') {
            if ('value' in props) {
                props.defaultValue = props.value;
                delete props.value;
            }

            if ('checked' in props) {
                props.defaultChecked = props.checked;
                delete props.checked;
            }
        }

        // A link or a form in the template must not navigate away from the editor.
        if (tag === 'a') {
            props.onClick = preventDefault;
        }

        if (tag === 'form') {
            props.onSubmit = preventDefault;
        }

        // An iframe swallows the clicks that select the block, as it does in core's
        // own embed previews.
        if (tag === 'iframe') {
            props.style = Object.assign({}, props.style, { pointerEvents: 'none' });
        }

        return props;
    }

    /**
     * Merge the block wrapper props onto the props of the template's root element.
     *
     * The classes and styles of both are kept. Everything else comes from the
     * wrapper props: the ref, id, tabIndex, role and data attributes are what the
     * editor relies on to select and move the block.
     *
     * @param {Object} props      Props of the root element.
     * @param {Object} blockProps Props from useBlockProps.
     * @return {Object} Merged props.
     */
    function mergeBlockProps(props, blockProps) {
        const merged = Object.assign({}, props, blockProps);
        const classes = [props.className, blockProps.className].filter(Boolean);

        if (classes.length > 0) {
            merged.className = classes.join(' ');
        }

        if (props.style || blockProps.style) {
            merged.style = Object.assign({}, props.style, blockProps.style);
        }

        return merged;
    }

    /**
     * Whether a node is text with something to show, or an element.
     *
     * Whitespace between elements and comments are not content: they must not stop
     * an element from counting as the only child, or as the root.
     *
     * @param {Node} node DOM node.
     * @return {boolean} Whether the node counts.
     */
    function isSignificant(node) {
        if (node.nodeType === 1) {
            return true;
        }

        return node.nodeType === 3 && node.nodeValue.trim() !== '';
    }

    /**
     * The nodes of a list that count, in order.
     *
     * @param {NodeList|Array} nodes DOM nodes.
     * @return {Array} Significant nodes.
     */
    function significantNodes(nodes) {
        return Array.prototype.filter.call(nodes || [], isSignificant);
    }

    /**
     * Whether an element's only content is the inner blocks marker.
     *
     * @param {Element} node DOM element.
     * @return {boolean} Whether the element hosts the inner blocks directly.
     */
    function hostsInnerBlocks(node) {
        const children = significantNodes(node.childNodes);

        return children.length === 1 && children[0].localName === INNER_BLOCKS_TAG;
    }

    /**
     * The template's element that holds the inner blocks, as a core container
     * block's own element does: the inner blocks are its direct children.
     *
     * @param {Object} props Tag name, element props and InnerBlocks props.
     * @return {Object} Element.
     */
    function InnerBlocksHost(props) {
        return el(props.tagName, useInnerBlocksProps(props.elementProps, props.innerBlocksProps));
    }

    /**
     * Convert a plain string attribute to the HTML a RichText holds.
     *
     * @param {*} value Attribute value.
     * @return {string} Escaped HTML.
     */
    function textToHtml(value) {
        return toInputValue(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');
    }

    /**
     * Convert the HTML a RichText hands back to a plain string attribute.
     *
     * @param {string} html RichText value.
     * @return {string} Text, with line breaks as newlines.
     */
    function htmlToText(html) {
        const document = new DOMParser().parseFromString(
            String(html).replace(/<br\s*\/?>/gi, '\n'),
            'text/html',
        );

        return document.body.textContent || '';
    }

    /**
     * The in-place editor of one attribute.
     *
     * A `richtext` attribute keeps the formatting the author applies, as HTML. Any
     * other string attribute is plain text: RichText edits it, but without
     * formatting, and the value goes back to the attribute as text, so a template
     * that prints it escaped keeps working.
     *
     * @param {string}   tagName  Tag of the marked element.
     * @param {Object}   props    Props of the marked element.
     * @param {string}   key      Attribute name.
     * @param {Object}   field    Editor field descriptor of the attribute.
     * @param {Object}   context  Conversion context.
     * @return {Object} RichText element.
     */
    function renderEditable(tagName, props, key, field, context) {
        const rich = field.control === 'richtext';
        const value = context.attributes[key];

        return el(
            RichText,
            Object.assign({}, props, {
                tagName: tagName,
                identifier: key,
                value: rich ? toInputValue(value) : textToHtml(value),
                placeholder: field.placeholder || field.label,
                allowedFormats: rich ? undefined : [],
                withoutInteractiveFormatting: !rich,
                disableLineBreaks: field.control === 'text',
                onChange: function (next) {
                    const patch = {};

                    patch[key] = rich ? next : htmlToText(next);
                    context.setAttributes(patch);
                },
            }),
        );
    }

    /**
     * Convert one DOM node of the server render to a React node.
     *
     * @param {Node}    node    DOM node.
     * @param {Object}  context Conversion context.
     * @param {Object}  [root]  Block wrapper props, for the template's root element.
     * @return {*} React node, or null.
     */
    function toReact(node, context, root) {
        if (node.nodeType === 3) {
            return node.nodeValue;
        }

        if (node.nodeType !== 1) {
            return null;
        }

        const tag = node.localName;

        if (DROPPED_ELEMENTS[tag]) {
            return null;
        }

        if (tag === INNER_BLOCKS_TAG) {
            return claimInnerBlocks(context) && context.innerBlocksProps ? el(InnerBlocks, context.innerBlocksProps) : null;
        }

        let props = toProps(node);

        if (root) {
            props = mergeBlockProps(props, root);
        }

        const key = node.getAttribute(EDITABLE_ATTRIBUTE);
        const field = key ? context.fields[key] : null;

        // A marked root element is a RichText with the wrapper props, as core/paragraph
        // spreads useBlockProps on its RichText.
        if (field && RichText) {
            context.found.editables[key] = true;

            return renderEditable(tag, props, key, field, context);
        }

        if (context.innerBlocksProps && useInnerBlocksProps && hostsInnerBlocks(node) && claimInnerBlocks(context)) {
            return el(InnerBlocksHost, {
                tagName: tag,
                elementProps: props,
                innerBlocksProps: context.innerBlocksProps,
            });
        }

        if (VOID_ELEMENTS[tag]) {
            return el(tag, props);
        }

        const children = Array.prototype.map.call(node.childNodes, function (child) {
            return toReact(child, context);
        });

        return el.apply(null, [tag, props].concat(children));
    }

    /**
     * Draw a server render on the canvas.
     *
     * @param {Document} document   Parsed server render.
     * @param {Object}   context    Conversion context.
     * @param {Object}   blockProps Props from useBlockProps.
     * @return {Object} Element.
     */
    function renderCanvas(document, context, blockProps) {
        const nodes = significantNodes(document.body.childNodes);
        const single = nodes.length === 1 && nodes[0].nodeType === 1 ? nodes[0] : null;
        // A root the editor drops or cannot give children to keeps a wrapper.
        const mergeable =
            single &&
            !DROPPED_ELEMENTS[single.localName] &&
            !VOID_ELEMENTS[single.localName] &&
            single.localName !== INNER_BLOCKS_TAG;

        if (mergeable) {
            const element = toReact(single, context, blockProps);

            return context.found.innerBlocks || !context.innerBlocksProps
                ? element
                : withMissingInnerBlocks(element, context);
        }

        // A template that is nothing but `content`: the block element holds the inner
        // blocks directly, as a core container does.
        if (single && single.localName === INNER_BLOCKS_TAG && context.innerBlocksProps && useInnerBlocksProps) {
            return el(InnerBlocksHost, {
                tagName: 'div',
                elementProps: blockProps,
                innerBlocksProps: context.innerBlocksProps,
            });
        }

        // Several root nodes, or a root the editor drops: one wrapper around them.
        const children = Array.prototype.map.call(document.body.childNodes, function (child) {
            return toReact(child, context);
        });

        if (context.innerBlocksProps && !context.found.innerBlocks) {
            children.push(el(InnerBlocks, context.innerBlocksProps));
        }

        return el.apply(null, ['div', blockProps].concat(children));
    }

    /**
     * Take the place of the inner blocks for the first marker of a render.
     *
     * A block has one list of inner blocks, so it mounts once. A template that prints
     * `content` twice keeps the first place, and the editor warns about the others.
     *
     * @param {Object} context Conversion context.
     * @return {boolean} Whether this marker mounts the inner blocks.
     */
    function claimInnerBlocks(context) {
        if (!context.found.innerBlocks) {
            context.found.innerBlocks = true;

            return true;
        }

        if (!context.found.duplicate && logger && logger.warn) {
            logger.warn(
                '[foehn] The template of "' +
                    context.name +
                    '" prints `content` more than once, so the editor shows the inner blocks at the first place only.',
            );
        }

        context.found.duplicate = true;

        return false;
    }

    /**
     * Add the inner blocks to a root element whose template never printed them.
     *
     * Without this, a container whose template forgot `content` would lose its inner
     * blocks in the editor, and saving would erase them.
     *
     * @param {Object} element Root element.
     * @param {Object} context Conversion context.
     * @return {Object} Element.
     */
    function withMissingInnerBlocks(element, context) {
        if (logger && logger.warn) {
            logger.warn(
                '[foehn] The template of "' +
                    context.name +
                    '" does not print `content`, so the editor adds the inner blocks at the end of its root element.',
            );
        }

        const children = [].concat(element.props.children === undefined ? [] : element.props.children);

        return cloneElement.apply(null, [element, {}].concat(children, [el(InnerBlocks, context.innerBlocksProps)]));
    }

    /**
     * The attributes a canvas render depends on, as a comparable string.
     *
     * @param {Object} attributes Block attributes.
     * @param {Object} onCanvas   Attribute names edited in place, left out.
     * @return {string} Signature.
     */
    function awaitedSignature(attributes, onCanvas) {
        const awaited = {};

        Object.keys(attributes || {}).forEach(function (key) {
            if (!onCanvas[key]) {
                awaited[key] = attributes[key];
            }
        });

        return JSON.stringify(awaited);
    }

    /**
     * Fetch the canvas render of a block, again each time its attributes change.
     *
     * The render of the attributes edited in place is not awaited: RichText already
     * shows what the author types, and a request per keystroke would redraw the
     * block under the cursor.
     *
     * @param {string} name       Block name.
     * @param {Object} attributes Current attributes.
     * @param {Object} onCanvas   Attribute names edited in place.
     * @return {Object} `html` (null until the first render) and `error`.
     */
    function useCanvasRender(name, attributes, onCanvas) {
        const postId = useSelect
            ? useSelect(function (select) {
                  const editor = select('core/editor');

                  return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : null;
              }, [])
            : null;

        const state = useState({ html: null, error: null });
        const render = state[0];
        const setRender = state[1];
        const request = useRef(0);
        const requested = useRef(null);
        const hasRender = render.html !== null;

        const signature = awaitedSignature(attributes, onCanvas);

        useEffect(
            function () {
                // Learning which attributes are edited in place changes the signature
                // without any change worth a render.
                if (requested.current && awaitedSignature(requested.current.attributes, onCanvas) === signature
                    && requested.current.postId === postId) {
                    return undefined;
                }

                const id = ++request.current;
                const timer = setTimeout(
                    function () {
                        // Noted when sent, not when scheduled: a cancelled timer sent nothing.
                        requested.current = { attributes: attributes, postId: postId };

                        apiFetch({
                            path:
                                '/wp/v2/block-renderer/' +
                                name +
                                '?context=edit&' +
                                CANVAS_PARAM +
                                '=1' +
                                (postId ? '&post_id=' + postId : ''),
                            method: 'POST',
                            data: { attributes: attributes },
                        }).then(
                            function (response) {
                                if (id === request.current) {
                                    setRender({ html: (response && response.rendered) || '', error: null });
                                }
                            },
                            function (error) {
                                if (id === request.current) {
                                    setRender(function (previous) {
                                        return {
                                            html: previous.html,
                                            error: (error && error.message) || 'The block could not be rendered.',
                                        };
                                    });
                                }
                            },
                        );
                    },
                    hasRender ? RENDER_DELAY : 0,
                );

                return function () {
                    clearTimeout(timer);
                };
            },
            [signature, postId],
        );

        // A response that lands after the block is gone has nothing to update.
        useEffect(function () {
            return function () {
                request.current = -1;
            };
        }, []);

        return render;
    }

    /**
     * The toolbar button that replaces an image, as on core's image block.
     *
     * @param {Object}   field    Editor field descriptor.
     * @param {*}        value    Current attachment id.
     * @param {Function} setValue Setter for the attribute.
     * @return {Object|null} Element.
     */
    function renderMediaReplace(field, value, setValue) {
        if (!MediaReplaceFlow) {
            return null;
        }

        return el(MediaReplaceFlow, {
            mediaId: value || undefined,
            allowedTypes: ['image'],
            accept: 'image/*',
            name: (value ? 'Replace ' : 'Add ') + field.label.toLowerCase(),
            onSelect: function (media) {
                setValue(media && media.id ? media.id : undefined);
            },
        });
    }

    /**
     * Build the toolbar controls of a block: one media button per image attribute.
     *
     * @param {Object}   definition    Block definition from the payload.
     * @param {Object}   attributes    Current block attributes.
     * @param {Function} setAttributes Attribute setter given to `edit`.
     * @return {Object|null} Element.
     */
    function renderToolbar(definition, attributes, setAttributes) {
        const fields = definition.attributes || {};
        const buttons = Object.keys(fields)
            .filter(function (key) {
                return fields[key] && fields[key].control === 'image';
            })
            .map(function (key) {
                const button = renderMediaReplace(fields[key], attributes[key], function (value) {
                    const patch = {};

                    patch[key] = value;
                    setAttributes(patch);
                });

                return button ? cloneElement(button, { key: definition.name + '/' + key }) : null;
            })
            .filter(Boolean);

        if (!BlockControls || buttons.length === 0) {
            return null;
        }

        return el.apply(null, [BlockControls, { group: 'other' }].concat(buttons));
    }

    /**
     * Build the `edit` component for one block.
     *
     * @param {Object} definition Block definition from the payload.
     * @return {Function} Edit component.
     */
    function createEdit(definition) {
        const innerBlocksProps = definition.innerBlocks ? toInnerBlocksProps(definition.innerBlocks) : null;

        return function FoehnBlockEdit(props) {
            const blockProps = useBlockProps();

            // Which attributes the last render let the author edit in place. Read
            // before the render to decide what a new request waits for.
            const onCanvas = useRef({});
            const render = useCanvasRender(definition.name, props.attributes, onCanvas.current);

            const document = useMemo(
                function () {
                    return render.html === null
                        ? null
                        : new DOMParser().parseFromString(render.html, 'text/html');
                },
                [render.html],
            );

            const context = {
                name: definition.name,
                attributes: props.attributes,
                setAttributes: props.setAttributes,
                fields: definition.attributes || {},
                innerBlocksProps: innerBlocksProps,
                found: { innerBlocks: false, duplicate: false, editables: {} },
            };

            let canvas;

            if (document) {
                canvas = renderCanvas(document, context, blockProps);
                onCanvas.current = context.found.editables;
            } else if (innerBlocksProps && render.error) {
                // Without a render, a container still shows and saves its inner blocks.
                canvas = el(InnerBlocksHost, {
                    tagName: 'div',
                    elementProps: blockProps,
                    innerBlocksProps: innerBlocksProps,
                });
            } else {
                canvas = el('div', blockProps, render.error ? el('p', null, render.error) : Spinner ? el(Spinner) : null);
            }

            const fields = renderFields(definition, props.attributes, props.setAttributes, context.found.editables);

            return el(
                Fragment,
                null,
                renderToolbar(definition, props.attributes, props.setAttributes),
                fields.length > 0
                    ? el(InspectorControls, null, el(PanelBody, { title: 'Settings' }, fields))
                    : null,
                canvas,
            );
        };
    }

    definitions.forEach(function (definition) {
        if (!definition || typeof definition.name !== 'string') {
            return;
        }

        try {
            const settings = { edit: createEdit(definition) };

            if (definition.innerBlocks) {
                // A container must serialise InnerBlocks.Content. The default `null` save
                // drops the inner markup, and then $content never reaches the Twig
                // template. Non-containers define no save at all: they stay fully dynamic.
                settings.save = function FoehnBlockSave() {
                    return el(InnerBlocks.Content);
                };
            }

            // Title, category, icon, description and the attribute schema all come from the
            // server-side block metadata WordPress already bootstrapped into the editor, so
            // only behaviour is registered here. Restating metadata would clobber it.
            registerBlockType(definition.name, settings);
        } catch (error) {
            // One malformed definition must not stop the other blocks from registering.
            if (window.console && window.console.error) {
                window.console.error('[foehn] Could not register block "' + definition.name + '".', error);
            }
        }
    });
})(window.wp);
