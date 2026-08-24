import { Editor } from '@tiptap/core';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';

document.addEventListener('alpine:init', () => {
    Alpine.data('miniEditor', () => {
        let editor = null;
        let updatingFromEditor = false;

        return {
            content: '',
            updatedAt: Date.now(),
            init() {
                editor = new Editor({
                    element: this.$refs.element,
                    extensions: [
                        StarterKit.configure({ link: false, underline: false }),
                        Link.configure({ openOnClick: false }),
                        Underline,
                    ],
                    content: this.content || '',
                    editorProps: {
                        attributes: {
                            class: 'tiptap content min-h-80 px-3 py-2.5 focus:outline-none',
                        },
                    },
                    onCreate: () => {
                        this.updatedAt = Date.now();
                    },
                    onSelectionUpdate: () => {
                        this.updatedAt = Date.now();
                    },
                    onUpdate: ({ editor: currentEditor }) => {
                        updatingFromEditor = true;
                        this.content = currentEditor.getHTML();
                        updatingFromEditor = false;
                        this.updatedAt = Date.now();
                    },
                });

                this.$watch('content', (content) => {
                    if (!editor || updatingFromEditor || editor.getHTML() === String(content || '')) {
                        return;
                    }

                    editor.commands.setContent(String(content || ''), false);
                    this.updatedAt = Date.now();
                });
            },
            destroy() {
                editor?.destroy();
                editor = null;
            },
            isActive(type) {
                this.updatedAt;
                return editor?.isActive(type) ?? false;
            },
            toggleBold() {
                editor?.chain().focus().toggleBold().run();
            },
            toggleItalic() {
                editor?.chain().focus().toggleItalic().run();
            },
            toggleUnderline() {
                editor?.chain().focus().toggleUnderline().run();
            },
            toggleBulletList() {
                editor?.chain().focus().toggleBulletList().run();
            },
            toggleOrderedList() {
                editor?.chain().focus().toggleOrderedList().run();
            },
            toggleLink() {
                if (!editor) return;

                const currentUrl = String(editor.getAttributes('link').href || '');
                const url = window.prompt('Link URL', currentUrl);
                if (url === null) return;
                if (url.trim() === '') {
                    editor.chain().focus().extendMarkRange('link').unsetLink().run();
                    return;
                }

                editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
            },
            undo() {
                editor?.chain().focus().undo().run();
            },
            redo() {
                editor?.chain().focus().redo().run();
            },
        };
    });
});
