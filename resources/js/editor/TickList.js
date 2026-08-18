import { BulletList } from '@tiptap/extension-list'

export const TickList = BulletList.extend({
    name: 'tickList',
    priority: 110,

    parseHTML() {
        return [{ tag: 'ul[data-list-style="ticks"]' }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['ul', { ...HTMLAttributes, 'data-list-style': 'ticks', class: 'sm-tick-list' }, 0]
    },

    addCommands() {
        return {
            toggleTickList: () => ({ commands }) => commands.toggleList(this.name, this.options.itemTypeName),
        }
    },
})
