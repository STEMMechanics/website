<template>
    <div class="editor">
        <EditorContent
            :editor="editor" />
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
    }
});

const emits = defineEmits(["update:modelValue"]);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
    ],
    onUpdate: () => {
        emits("update:modelValue", editor.value.getHTML());
    }
});

onBeforeUnmount(() => {
    editor.value.destroy();
})

watch(
    () => props.modelValue,
    (newValue) => {
        const isSame = editor.value.getHTML() === newValue;

        if (isSame) {
            return;
        }

        editor.value.commands.setContent(newValue, false);
    }
);
</script>

<style lang="scss">
.ProseMirror {
    border-width: 1px;
    border-radius: 0.5rem;
    border-color: rgba(156,163,175,1);
    color: rgba(75,85,99,1);
    padding: 1rem;
    background-color: rgba(255,255,255,1);
    height: 24rem;
    overflow: scroll;
}

// w-full text-gray-6 flex-1 px-4 text-lg pt-5 border-gray border-1 rounded-l-2 rounded-r-2 bg-white
</style>