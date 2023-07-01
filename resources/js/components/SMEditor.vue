<template>
    <div class="editor">
        <MdEditor
            :preview="false"
            language="en-US"
            v-model="markdown"
            @change="handleChange" />
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { api } from "../helpers/api";
import { MediaCollection, MediaResponse } from "../helpers/api.types";
import { routes } from "../router";
import { urlMatches } from "../helpers/url";
import { mediaGetVariantUrl } from "../helpers/media";

interface PageList {
    title: string;
    value: string;
}

import { MdEditor } from "md-editor-v3";
import "md-editor-v3/lib/style.css";

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const emits = defineEmits(["input", "update:modelValue", "blur", "focus"]);

const markdown = ref(props.modelValue);
let timeout = null;

const handleChange = (newValue) => {
    if (timeout != null) {
        clearTimeout(timeout);
    }
    timeout = setTimeout(() => {
        timeout = null;
        emits("update:modelValue", newValue);
    }, 50);
};

watch(
    () => props.modelValue,
    (newValue) => {
        markdown.value = newValue;
    }
);
</script>

<style lang="scss">
.md-editor {
    border-color: rgba(156, 163, 175);
    border-radius: 0.5rem;

    .md-editor-toolbar-wrapper {
        height: 2.5rem;
        border-bottom-color: rgba(156, 163, 175);

        .md-editor-toolbar-item {
            height: 1.75rem;
        }

        .md-editor-icon {
            height: 1.75rem;
            width: 1.75rem;
        }
    }

    .cm-editor {
        font-size: 1rem;
    }

    .md-editor-footer {
        height: 2.5rem;
        font-size: 0.9rem;
        border-top-color: rgba(156, 163, 175);

        .md-editor-checkbox {
            width: 1rem;
            height: 1rem;
        }
    }
}
</style>
