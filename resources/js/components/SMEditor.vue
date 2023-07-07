<template>
    <div class="sm-html">
        <div
            v-if="editor"
            class="flex bg-white border-t border-x border-gray rounded-t-2">
            <button
                @click.prevent="editor.chain().focus().toggleInfo().run()"
                :class="[
                    'flex',
                    'flex-items-center',
                    'p-1',
                    'hover-bg-gray-3',
                    editor.isActive('info')
                        ? ['bg-sky-6', 'text-white']
                        : ['bg-white', 'text-gray-6'],
                ]">
                III
            </button>
            <div class="flex px-1 border-r border-gray relative">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 -960 960 960"
                    class="absolute right-1 top-1.5 h-6 pointer-events-none">
                    <path
                        d="M480-360 280-559h400L480-360Z"
                        fill="currentColor" />
                </svg>
                <select
                    class="appearance-none pl-3 pr-7 text-xs outline-none select-none bg-white"
                    @change="updateNode">
                    <option
                        value="paragraph"
                        :selected="editor.isActive('paragraph')">
                        Paragraph
                    </option>
                    <option
                        value="h1"
                        :selected="editor.isActive('heading', { level: 1 })">
                        Heading 1
                    </option>
                    <option
                        value="h2"
                        :selected="editor.isActive('heading', { level: 2 })">
                        Heading 2
                    </option>
                    <option
                        value="h3"
                        :selected="editor.isActive('heading', { level: 3 })">
                        Heading 3
                    </option>
                    <option
                        value="h4"
                        :selected="editor.isActive('heading', { level: 4 })">
                        Heading 4
                    </option>
                    <option
                        value="h5"
                        :selected="editor.isActive('heading', { level: 5 })">
                        Heading 5
                    </option>
                    <option
                        value="h6"
                        :selected="editor.isActive('heading', { level: 6 })">
                        Heading 6
                    </option>
                </select>
            </div>
            <div class="flex p-1 border-r border-gray">
                <button
                    @click.prevent="editor.chain().focus().toggleBold().run()"
                    :disabled="!editor.can().chain().focus().toggleBold().run()"
                    title="bold"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('bold')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
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
                    :disabled="
                        !editor.can().chain().focus().toggleItalic().run()
                    "
                    title="italic"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('italic')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M10,4V7H12.21L8.79,15H6V18H14V15H11.79L15.21,7H18V4H10Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleUnderline().run()
                    "
                    :disabled="
                        !editor.can().chain().focus().toggleUnderline().run()
                    "
                    title="underline"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('underline')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <title>format-underline</title>
                        <path
                            d="M5,21H19V19H5V21M12,17A6,6 0 0,0 18,11V3H15.5V11A3.5,3.5 0 0,1 12,14.5A3.5,3.5 0 0,1 8.5,11V3H6V11A6,6 0 0,0 12,17Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="editor.chain().focus().toggleStrike().run()"
                    :disabled="
                        !editor.can().chain().focus().toggleStrike().run()
                    "
                    title="strike"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('strike')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <title>format-strikethrough-variant</title>
                        <path
                            d="M23,12V14H18.61C19.61,16.14 19.56,22 12.38,22C4.05,22.05 4.37,15.5 4.37,15.5L8.34,15.55C8.37,18.92 11.5,18.92 12.12,18.88C12.76,18.83 15.15,18.84 15.34,16.5C15.42,15.41 14.32,14.58 13.12,14H1V12H23M19.41,7.89L15.43,7.86C15.43,7.86 15.6,5.09 12.15,5.08C8.7,5.06 9,7.28 9,7.56C9.04,7.84 9.34,9.22 12,9.88H5.71C5.71,9.88 2.22,3.15 10.74,2C19.45,0.8 19.43,7.91 19.41,7.89Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r border-gray">
                <button
                    @click.prevent="
                        editor.chain().focus().toggleBulletList().run()
                    "
                    title="bullet list"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('bulletList')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleOrderedList().run()
                    "
                    title="ordered list"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('orderedList')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <title>format-list-numbered</title>
                        <path
                            d="M7,13V11H21V13H7M7,19V17H21V19H7M7,7V5H21V7H7M3,8V5H2V4H4V8H3M2,17V16H5V20H2V19H4V18.5H3V17.5H4V17H2M4.25,10A0.75,0.75 0 0,1 5,10.75C5,10.95 4.92,11.14 4.79,11.27L3.12,13H5V14H2V13.08L4,11H2V10H4.25Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r border-gray">
                <button
                    @click.prevent="
                        editor.chain().focus().toggleCodeBlock().run()
                    "
                    title="code block"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('codeBlock')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M14.6,16.6L19.2,12L14.6,7.4L16,6L22,12L16,18L14.6,16.6M9.4,16.6L4.8,12L9.4,7.4L8,6L2,12L8,18L9.4,16.6Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleBlockquote().run()
                    "
                    title="blockquote"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('blockquote')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M10,7L8,11H11V17H5V11L7,7H10M18,7L16,11H19V17H13V11L15,7H18Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().setHorizontalRule().run()
                    "
                    title="horizontal rule"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'bg-white',
                        'hover-bg-gray-3',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path d="M19,13H5V11H19V13Z" fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r border-gray">
                <button
                    @click.prevent="editor.chain().focus().setHardBreak().run()"
                    title="hard break"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M10,11A4,4 0 0,1 6,7A4,4 0 0,1 10,3H18V5H16V21H14V5H12V21H10V11Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().unsetAllMarks().run();
                        editor.chain().focus().clearNodes().run();
                    "
                    title="Clear formatting"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M6,5V5.18L8.82,8H11.22L10.5,9.68L12.6,11.78L14.21,8H20V5H6M3.27,5L2,6.27L8.97,13.24L6.5,19H9.5L11.07,15.34L16.73,21L18,19.73L3.55,5.27L3.27,5Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1">
                <button
                    @click.prevent="editor.chain().focus().undo().run()"
                    title="Undo"
                    :disabled="!editor.can().chain().focus().undo().run()"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                        [
                            'disabled-text-gray',
                            'hover-disabled-bg-transparent',
                            'disabled-cursor-not-allowed',
                        ],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <title>undo</title>
                        <path
                            d="M12.5,8C9.85,8 7.45,9 5.6,10.6L2,7V16H11L7.38,12.38C8.77,11.22 10.54,10.5 12.5,10.5C16.04,10.5 19.05,12.81 20.1,16L22.47,15.22C21.08,11.03 17.15,8 12.5,8Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="editor.chain().focus().redo().run()"
                    title="Redo"
                    :disabled="!editor.can().chain().focus().redo().run()"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                        [
                            'disabled-text-gray',
                            'hover-disabled-bg-transparent',
                            'disabled-cursor-not-allowed',
                        ],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <title>redo</title>
                        <path
                            d="M18.4,10.6C16.55,9 14.15,8 11.5,8C6.85,8 2.92,11.03 1.54,15.22L3.9,16C4.95,12.81 7.95,10.5 11.5,10.5C13.45,10.5 15.23,11.22 16.62,12.38L13,16H22V7L18.4,10.6Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
        </div>
        <EditorContent
            :editor="editor"
            class="rounded-b-2 bg-white p-4 border-1 border-gray h-128 overflow-auto sm-editor" />
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from "vue";
import { useEditor, EditorContent } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Underline from "@tiptap/extension-underline";
import TextAlign from "@tiptap/extension-text-align";
import { Info } from "../extensions/info";

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
});

const emits = defineEmits(["update:modelValue"]);

const editor = useEditor({
    content: props.modelValue,
    extensions: [StarterKit, Underline, TextAlign, Info],
    onUpdate: () => {
        emits("update:modelValue", editor.value.getHTML());
    },
});

const updateNode = (event) => {
    if (event.target.value) {
        switch (event.target.value) {
            case "paragraph":
                editor.value.chain().focus().setParagraph().run();
                break;
            case "h1":
                editor.value.chain().focus().setHeading({ level: 1 }).run();
                break;
            case "h2":
                editor.value.chain().focus().setHeading({ level: 2 }).run();
                break;
            case "h3":
                editor.value.chain().focus().setHeading({ level: 3 }).run();
                break;
            case "h4":
                editor.value.chain().focus().setHeading({ level: 4 }).run();
                break;
            case "h5":
                editor.value.chain().focus().setHeading({ level: 5 }).run();
                break;
            case "h6":
                editor.value.chain().focus().setHeading({ level: 6 }).run();
                break;
        }
    }
};

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
