import {mergeAttributes, Node} from '@tiptap/core'


export const ExtraSmall = Node.create({
    name: 'extraSmall',

    priority: 1000,

    addOptions() {
        return {
            HTMLAttributes: {
                class: 'text-xs',
            },
        }
    },

    group: 'block',

    content: 'inline*',

    parseHTML() {
        return [{ tag: 'p.text-xs' }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['p', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0]
    },

    addCommands() {
        return {
            setExtraSmall: () => ({ commands }) => {
                return commands.setNode(this.name)
            },
        }
    },
})
