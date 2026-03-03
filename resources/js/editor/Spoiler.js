import {Mark, mergeAttributes} from '@tiptap/core'

export const Spoiler = Mark.create({
    name: 'spoiler',

    parseHTML() {
        return [
            {
                tag: 'span[data-spoiler]',
            },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes({ class: 'spoiler', 'data-spoiler': 'true' }, HTMLAttributes), 0]
    },

    addCommands() {
        return {
            toggleSpoiler: () => ({ commands }) => commands.toggleMark(this.name),
        }
    },
})
