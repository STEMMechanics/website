<template>
    <div class="sm-editor">
        <div
            v-if="editor"
            class="flex bg-white p-1 border-t border-x border-gray rounded-t-2">
            <button
                @click.prevent="editor.chain().focus().toggleBold().run()"
                :disabled="!editor.can().chain().focus().toggleBold().run()"
                :class="[
                    'flex',
                    editor.isActive('bold')
                        ? ['bg-sky-6', 'text-white']
                        : ['bg-white', 'text-gray-6'],
                    'p-1',
                    'hover-bg-gray-3',
                ]">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    viewBox="0 0 24 24">
                    <path
                        d="M13.5,15.5H10V12.5H13.5A1.5,1.5 0 0,1 15,14A1.5,1.5 0 0,1 13.5,15.5M10,6.5H13A1.5,1.5 0 0,1 14.5,8A1.5,1.5 0 0,1 13,9.5H10M15.6,10.79C16.57,10.11 17.25,9 17.25,8C17.25,5.74 15.5,4 13.25,4H7V18H14.04C16.14,18 17.75,16.3 17.75,14.21C17.75,12.69 16.89,11.39 15.6,10.79Z"
                        fill="currentColor" />
                </svg>
            </button>
            <button
                @click.prevent="editor.chain().focus().toggleItalic().run()"
                :disabled="!editor.can().chain().focus().toggleItalic().run()"
                :class="{ 'is-active': editor.isActive('italic') }">
                italic
            </button>
            <button
                @click.prevent="editor.chain().focus().toggleStrike().run()"
                :disabled="!editor.can().chain().focus().toggleStrike().run()"
                :class="{ 'is-active': editor.isActive('strike') }">
                strike
            </button>
            <button
                @click.prevent="editor.chain().focus().toggleCode().run()"
                :disabled="!editor.can().chain().focus().toggleCode().run()"
                :class="{ 'is-active': editor.isActive('code') }">
                code
            </button>
            <button
                @click.prevent="editor.chain().focus().unsetAllMarks().run()">
                clear marks
            </button>
            <button @click.prevent="editor.chain().focus().clearNodes().run()">
                clear nodes
            </button>
            <button
                @click.prevent="editor.chain().focus().setParagraph().run()"
                :class="{ 'is-active': editor.isActive('paragraph') }">
                paragraph
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleHeading({ level: 1 }).run()
                "
                :class="{
                    'is-active': editor.isActive('heading', { level: 1 }),
                }">
                h1
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
                :class="{
                    'is-active': editor.isActive('heading', { level: 2 }),
                }">
                h2
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                "
                :class="{
                    'is-active': editor.isActive('heading', { level: 3 }),
                }">
                h3
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleHeading({ level: 4 }).run()
                "
                :class="{
                    'is-active': editor.isActive('heading', { level: 4 }),
                }">
                h4
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleHeading({ level: 5 }).run()
                "
                :class="{
                    'is-active': editor.isActive('heading', { level: 5 }),
                }">
                h5
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleHeading({ level: 6 }).run()
                "
                :class="{
                    'is-active': editor.isActive('heading', { level: 6 }),
                }">
                h6
            </button>
            <button
                @click.prevent="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'is-active': editor.isActive('bulletList') }">
                bullet list
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().toggleOrderedList().run()
                "
                :class="{ 'is-active': editor.isActive('orderedList') }">
                ordered list
            </button>
            <button
                @click.prevent="editor.chain().focus().toggleCodeBlock().run()"
                :class="{ 'is-active': editor.isActive('codeBlock') }">
                code block
            </button>
            <button
                @click.prevent="editor.chain().focus().toggleBlockquote().run()"
                :class="{ 'is-active': editor.isActive('blockquote') }">
                blockquote
            </button>
            <button
                @click.prevent="
                    editor.chain().focus().setHorizontalRule().run()
                ">
                horizontal rule
            </button>
            <button
                @click.prevent="editor.chain().focus().setHardBreak().run()">
                hard break
            </button>
            <button
                @click.prevent="editor.chain().focus().undo().run()"
                :disabled="!editor.can().chain().focus().undo().run()">
                undo
            </button>
            <button
                @click.prevent="editor.chain().focus().redo().run()"
                :disabled="!editor.can().chain().focus().redo().run()">
                redo
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from "vue";
import { useEditor, EditorContent } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
});

const emits = defineEmits(["update:modelValue"]);

const editor = useEditor({
    content: props.modelValue,
    extensions: [StarterKit],
    onUpdate: () => {
        emits("update:modelValue", editor.value.getHTML());
    },
});

onBeforeUnmount(() => {
    editor.value.destroy();
});

watch(
    () => props.modelValue,
    (newValue) => {
        const isSame = editor.value.getHTML() === newValue;

        if (isSame) {
            return;
        }

        editor.value.commands.setContent(newValue, false);
    },
);
</script>

<style lang="scss">
.sm-editor {
    .toolbar {
        // display: flex;
        // border-width: 1px 1px 0 1px;
        // border-radius: 0.5rem 0.5rem 0 0;
        // border-color: rgba(156, 163, 175, 1);
        // background-color: rgba(255, 255, 255, 1);

        button {
            display: flex;
            background-color: rgba(255, 255, 255, 1);
            color: rgba(75, 85, 99, 1);
            padding: 0.25rem;
            margin: 0.25rem;
            background-color: rgba(255, 255, 255, 1);

            svg {
                height: 1.2rem;
                width: 1.2rem;
            }
        }

        button.is-active {
            background-color: red;
        }
    }

    .ProseMirror {
        border-width: 1px;
        border-radius: 0 0 0.5rem 0.5rem;
        border-color: rgba(156, 163, 175, 0.5) rgba(156, 163, 175, 1)
            rgba(156, 163, 175, 1);
        color: rgba(75, 85, 99, 1);
        padding: 1rem;
        background-color: rgba(255, 255, 255, 1);
        height: 24rem;
        overflow: scroll;
        outline: none;
    }
}
</style>
