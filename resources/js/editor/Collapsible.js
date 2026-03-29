import { mergeAttributes, Node } from '@tiptap/core';

const DEFAULT_TITLE = 'Section';

const updateNodeAttributes = (editor, getPos, node, nextAttrs = {}) => {
    const pos = typeof getPos === 'function' ? getPos() : null;
    if (typeof pos !== 'number') {
        return;
    }

    const tr = editor.view.state.tr.setNodeMarkup(pos, undefined, {
        ...node.attrs,
        ...nextAttrs,
    });

    editor.view.dispatch(tr);
};

const promptForTitle = (currentTitle) => {
    if (typeof window.prompt !== 'function') {
        return currentTitle;
    }

    const nextTitle = window.prompt('Collapsible section title', currentTitle);
    if (nextTitle === null) {
        return null;
    }

    return String(nextTitle || '').trim() || DEFAULT_TITLE;
};

export const Collapsible = Node.create({
    name: 'collapsibleSection',

    addOptions() {
        return {
            HTMLAttributes: {
                class: 'sm-collapsible-node',
            },
            defaultTitle: DEFAULT_TITLE,
        };
    },

    addAttributes() {
        return {
            title: {
                default: this.options.defaultTitle,
                parseHTML: (element) => String(element.getAttribute('data-title') || '').trim() || this.options.defaultTitle,
                renderHTML: (attributes) => ({
                    'data-title': String(attributes.title || this.options.defaultTitle),
                }),
            },
            open: {
                default: false,
                parseHTML: (element) => element.hasAttribute('open'),
                renderHTML: (attributes) => (attributes.open === true ? { open: '' } : {}),
            },
        };
    },

    content: 'block+',
    group: 'block',
    defining: true,

    parseHTML() {
        return [
            {
                tag: 'details[data-type="collapsible-section"]',
                contentElement: (element) => element.querySelector('.sm-collapsible-node__content') || element,
            },
        ];
    },

    renderHTML({ node, HTMLAttributes }) {
        const title = String(node.attrs.title || this.options.defaultTitle).trim() || this.options.defaultTitle;
        const open = node.attrs.open === true ? { open: '' } : {};

            return [
            'details',
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                'data-type': 'collapsible-section',
                'data-title': title,
            }, open),
            ['summary', { class: 'sm-collapsible-node__summary' },
                ['span', { class: 'sm-collapsible-node__summary-title' }, title],
            ],
            ['div', { class: 'sm-collapsible-node__content' }, 0],
        ];
    },

    addCommands() {
        return {
            insertCollapsibleSection:
                (attributes = {}) =>
                    ({ chain }) => {
                        const title = String(attributes.title || this.options.defaultTitle).trim() || this.options.defaultTitle;
                        return chain().focus().insertContent({
                            type: this.name,
                            attrs: {
                                title,
                                open: attributes.open === true ? true : false,
                            },
                            content: [{ type: 'paragraph' }],
                        }).run();
                    },
        };
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const defaultTitle = this.options.defaultTitle;
            const wrapper = document.createElement('details');
            wrapper.className = 'sm-collapsible-node';
            wrapper.open = node.attrs.open === true;

            const summary = document.createElement('summary');
            summary.className = 'sm-collapsible-node__summary';

            const titleWrap = document.createElement('span');
            titleWrap.className = 'sm-collapsible-node__summary-title';

            const titleLabel = document.createElement('span');
            titleLabel.className = 'sm-collapsible-node__summary-text';
            titleLabel.textContent = String(node.attrs.title || defaultTitle).trim() || defaultTitle;

            const chevron = document.createElement('span');
            chevron.className = 'sm-collapsible-node__chevron';
            chevron.setAttribute('aria-hidden', 'true');
            chevron.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>';

            const actions = document.createElement('span');
            actions.className = 'sm-collapsible-node__summary-actions';
            actions.contentEditable = 'false';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'sm-collapsible-node__button';
            editButton.textContent = 'Edit';

            const content = document.createElement('div');
            content.className = 'sm-collapsible-node__content';

            titleWrap.append(chevron, titleLabel);
            actions.append(editButton);
            summary.append(titleWrap, actions);
            wrapper.append(summary, content);

            const syncTitle = (nextTitle) => {
                const title = String(nextTitle || '').trim() || defaultTitle;
                titleLabel.textContent = title;
                updateNodeAttributes(editor, getPos, node, { title });
            };

            const syncOpen = (nextOpen) => {
                wrapper.open = Boolean(nextOpen);
                chevron.style.transform = wrapper.open ? 'rotate(90deg)' : 'rotate(0deg)';
                updateNodeAttributes(editor, getPos, node, { open: wrapper.open });
            };

            editButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const nextTitle = promptForTitle(titleLabel.textContent || defaultTitle);
                if (nextTitle === null) {
                    return;
                }
                syncTitle(nextTitle);
            });

            wrapper.addEventListener('toggle', () => {
                if (wrapper.open !== (node.attrs.open !== false)) {
                    syncOpen(wrapper.open);
                }
            });

            return {
                dom: wrapper,
                contentDOM: content,
                update(updatedNode) {
                    if (updatedNode.type.name !== node.type.name) {
                        return false;
                    }

                    node = updatedNode;
                    const title = String(updatedNode.attrs.title || defaultTitle).trim() || defaultTitle;
                    titleLabel.textContent = title;
                    wrapper.open = updatedNode.attrs.open === true;
                    chevron.style.transform = wrapper.open ? 'rotate(90deg)' : 'rotate(0deg)';
                    return true;
                },
                ignoreMutation(mutation) {
                    return !(mutation.target instanceof globalThis.Node) || !content.contains(mutation.target);
                },
            };
        };
    },
});
