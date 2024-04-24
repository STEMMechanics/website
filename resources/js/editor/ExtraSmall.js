import {mergeAttributes, Node} from '@tiptap/core'


export const ExtraSmall = Node.create({
    name: 'extraSmall',

    addOptions() {
        return {
            HTMLAttributes: {
                class: 'text-xs pb-4',
            },
        }
    },

    group: 'block',

    content: 'inline*',

    parseHTML() {
        return [{ tag: 'p.text-xs.pb-4' }]
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
