import {mergeAttributes, Node} from '@tiptap/core'


export const Small = Node.create({
    name: 'small',

    priority: 2000,

    addOptions() {
        return {
            HTMLAttributes: {
                class: 'text-sm',
            },
        }
    },

    group: 'block',

    content: 'inline*',

    parseHTML() {
        return [{
            tag: 'p.text-sm',
        }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['p', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0]
    },

    addCommands() {
        return {
            setSmall: () => ({ commands }) => {
                return commands.setNode(this.name)
            },
        }
    },
})
