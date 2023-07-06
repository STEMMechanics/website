<template>
    <div class="editor">
        <EditorContent
            :editor="editor" />
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from "vue";
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
