import {mergeAttributes, Node} from '@tiptap/core'


export const Box = Node.create({
    name: 'box',

    addOptions() {
        return {
            types: ['info', 'warning', 'danger', 'success', 'bug'],
            HTMLAttributes: {
                class: 'box',
            },
        }
    },

    addAttributes() {
        return {
            type: {
                default: 'info',
                rendered: false,
            },
        }
    },

    group: 'block',

    content: 'inline*',

    defining: true,

    parseHTML() {
        return this.options.types.map((type) => ({
            tag: `div.box.${type}`,
            getAttrs: (node) => {
                const classList = node.getAttribute('class')?.split(' ') || [];
                const boxType = classList.find(cls => this.options.types.includes(cls));
                return {
                    type: boxType || this.options.types[0],
                };
            },
        }));
    },

    renderHTML({ node, HTMLAttributes }) {
        const hasType = this.options.types.includes(node.attrs.type);
        const type = hasType
            ? node.attrs.type
            : this.options.types[0]

        let classes = 'box ' + type;
        return ['div', mergeAttributes({ class: classes, 'data-box-type': type }, HTMLAttributes), 0]
    },

    addCommands() {
        return {
            setBox: attributes => ({ commands }) => {
                if (!this.options.types.includes(attributes.type)) {
                    return false
                }

                return commands.setNode(this.name, attributes)
            },
            toggleBox: attributes => ({ commands }) => {
                if (!this.options.types.includes(attributes.type)) {
                    return false
                }

                return commands.toggleNode(this.name, 'paragraph', attributes)
            },
        }
    },
})
