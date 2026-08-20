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
    const registerBlockType = wp.blocks.registerBlockType;
    const InspectorControls = wp.blockEditor.InspectorControls;
    const InnerBlocks = wp.blockEditor.InnerBlocks;
    const MediaUpload = wp.blockEditor.MediaUpload;
    const MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
    const useBlockProps = wp.blockEditor.useBlockProps;
    const BaseControl = wp.components.BaseControl;
    const Button = wp.components.Button;
    const ComboboxControl = wp.components.ComboboxControl;
    const Disabled = wp.components.Disabled;
    const PanelBody = wp.components.PanelBody;
    const SelectControl = wp.components.SelectControl;
    const TextControl = wp.components.TextControl;
    const TextareaControl = wp.components.TextareaControl;
    const ToggleControl = wp.components.ToggleControl;

    // The `file` and `posts` controls name what they hold — a filename, a post
    // title — which means reading entity records the editor has already loaded.
    // `wp.data` ships with the editor; guarding anyway keeps this file harmless
    // wherever it is loaded.
    const useSelect = wp.data && wp.data.useSelect;
    const useState = wp.element.useState;

    // The global is the module exports object, so the component sits under a named or a
    // default export depending on the WordPress version. The bare namespace is the last
    // resort: older builds exposed the component itself as the global.
    const ServerSideRender =
        wp.serverSideRender &&
        (wp.serverSideRender.ServerSideRender || wp.serverSideRender.default || wp.serverSideRender);

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
     * @return {Array} Control elements, possibly empty.
     */
    function renderFields(definition, attributes, setAttributes) {
        const fields = definition.attributes || {};

        return Object.keys(fields).reduce(function (elements, key) {
            const field = fields[key] || {};
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

    /**
     * Build the `edit` component for one block.
     *
     * Whether the block is a container is fixed at registration, so the branch is taken
     * once here rather than inside the component. That keeps the hook calls
     * unconditional, which React requires.
     *
     * @param {Object} definition Block definition from the payload.
     * @return {Function} Edit component.
     */
    function createEdit(definition) {
        const innerBlocksProps = definition.innerBlocks ? toInnerBlocksProps(definition.innerBlocks) : null;

        return function FoehnBlockEdit(props) {
            // apiVersion 3 blocks must spread useBlockProps onto their wrapper element,
            // otherwise selection, alignment and the iframe styles never apply.
            const blockProps = useBlockProps();
            const fields = renderFields(definition, props.attributes, props.setAttributes);

            let canvas = null;

            if (innerBlocksProps) {
                canvas = el(InnerBlocks, innerBlocksProps);
            } else if (ServerSideRender) {
                // Every Føhn block is dynamic, so the block's own render_callback gives the
                // editor the real Twig output for free.
                canvas = el(ServerSideRender, { block: definition.name, attributes: props.attributes });

                // That output is real DOM: a link in the template would navigate away from
                // the editor, and every focusable element in every preview would sit in the
                // tab order. `Disabled` renders it inert, the same guard core uses on its own
                // server-rendered previews.
                if (Disabled) {
                    canvas = el(Disabled, null, canvas);
                }
            }

            return el(
                Fragment,
                null,
                fields.length > 0
                    ? el(InspectorControls, null, el(PanelBody, { title: 'Settings' }, fields))
                    : null,
                el('div', blockProps, canvas),
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
