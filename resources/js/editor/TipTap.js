import Link from "@tiptap/extension-link";
import {Editor} from "@tiptap/core";
import StarterKit from "@tiptap/starter-kit";
import Highlight from "@tiptap/extension-highlight";
import TextAlign from "@tiptap/extension-text-align";
import Typography from "@tiptap/extension-typography";
import {ColorHighlighter} from "./ColourHighter.js";
import {SmileyReplacer} from "./SmileyReplacer.js";
import {Small} from "./Small.js";
import {ExtraSmall} from "./ExtraSmall.js";
import {Box} from "./Box.js";

const editorToggleLink = (editor) => {
    const previousUrl = editor.getAttributes('link').href
    const url = window.prompt('URL', previousUrl)

    if (url === null) {
        return
    }

    if (url === '') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run()
        return
    }

    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const CustomLink = Link.extend({
    addKeyboardShortcuts() {
        return {
            'Mod-k': () => editorToggleLink(this.editor)
        }
    }
});

document.addEventListener('alpine:init', () => {
    Alpine.data('editor', (content) => {
        let editor // Alpine's reactive engine automatically wraps component properties in proxy objects. Attempting to use a proxied editor instance to apply a transaction will cause a "Range Error: Applying a mismatched transaction", so be sure to unwrap it using Alpine.raw(), or simply avoid storing your editor as a component property, as shown in this example.

        return {
            updatedAt: Date.now(), // force Alpine to rerender on selection change
            content: SM.decodeHtml(content),
            init() {
                const _this = this

                editor = new Editor({
                    element: this.$refs.element,
                    extensions: [
                        StarterKit,
                        Highlight,
                        CustomLink.configure({
                            rel: 'noopener noreferrer',
                            openOnClick: 'whenNotEditable',
                        }),
                        TextAlign.configure({
                            types: ['heading', 'paragraph', 'small', 'extraSmall'],
                        }),
                        Typography,
                        ColorHighlighter,
                        SmileyReplacer,
                        Small,
                        ExtraSmall,
                        Box
                    ],
                    content: content,
                    onCreate({/* editor */}) {
                        _this.updatedAt = Date.now()
                    },
                    onUpdate({editor}) {
                        _this.updatedAt = Date.now()
                        _this.content = editor.getHTML()
                    },
                    onSelectionUpdate({/* editor */}) {
                        _this.updatedAt = Date.now()
                    }
                })
            },
            isLoaded() {
                return editor
            },
            isActive(type, opts = {}) {
                return editor.isActive(type, opts)
            },
            toggleHeading(opts) {
                editor.chain().toggleHeading(opts).focus().run()
            },
            toggleBold() {
                editor.chain().toggleBold().focus().run()
            },
            toggleItalic() {
                editor.chain().toggleItalic().focus().run()
            },
            toggleUnderline() {
                editor.chain().toggleUnderline().focus().run()
            },
            toggleStrike() {
                editor.chain().toggleStrike().focus().run()
            },
            setParagraph() {
                editor.chain().setParagraph().focus().run()
            },
            toggleCode() {
                editor.chain().toggleCode().focus().run()
            },
            toggleBulletList() {
                editor.chain().toggleBulletList().focus().run()
            },
            toggleOrderedList() {
                editor.chain().toggleOrderedList().focus().run()
            },
            toggleBlockquote() {
                editor.chain().toggleBlockquote().focus().run()
            },
            toggleCodeBlock() {
                editor.chain().toggleCodeBlock().focus().run()
            },
            toggleLink() {
                editorToggleLink(editor)
            },
            clearLink() {
                editor.chain().focus().extendMarkRange('link').unsetLink().run()
            },
            toggleHighlight() {
                editor.chain().toggleHighlight().focus().run()
            },
            toggleSubscript() {
                editor.chain().toggleSubscript().focus().run()
            },
            toggleSuperscript() {
                editor.chain().toggleSuperscript().focus().run()
            },
            undo() {
                editor.chain().undo().focus().run()
            },
            redo() {
                editor.chain().redo().focus().run()
            },
            unsetAllMarks() {
                editor.chain().focus().unsetAllMarks().run()
            },
            clearNotes() {
                editor.chain().focus().clearNodes().run()
            },
            setTextAlign(value) {
                editor.chain().setTextAlign(value).focus().run()
            },
            setSmall() {
                editor.chain().focus().setSmall().run()
            },
            setExtraSmall() {
                editor.chain().focus().setExtraSmall().run()
            },
            toggleBox(opts) {
                editor.chain().toggleBox(opts).focus().run()
            },
        }
    })
})
