import {mergeAttributes, Node} from '@tiptap/core'


export const Small = Node.create({
    name: 'small',

    addOptions() {
        return {
            HTMLAttributes: {
                class: 'text-sm pb-4',
            },
        }
    },

    group: 'block',

    content: 'inline*',

    parseHTML() {
        return [{
            tag: 'div.text-sm.pb-4',
        }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0]
    },

    addCommands() {
        return {
            setSmall: () => ({ commands }) => {
                return commands.setNode(this.name)
            },
        }
    },
})
