<template>
    <a :href="`#${id}`" class="header">
        <h3>
            {{ props.text }}
            <span class="hash" @click.prevent="copyAnchor">#</span>
        </h3>
    </a>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useToastStore } from "../store/ToastStore";

const props = defineProps({
    text: {
        type: String,
        required: true,
    },
    id: {
        type: String,
        default: "",
        required: false,
    },
});

const computedHeaderId = (text: string): string => {
    return text.replace(/[^a-zA-Z0-9]+/g, "-").toLowerCase();
};

const id = ref(
    props.id && props.id.length > 0 ? props.id : computedHeaderId(props.text)
);

const copyAnchor = () => {
    const currentUrl = window.location.href.replace(/#.*/, "");
    const newUrl = currentUrl + "#" + id.value;

    navigator.clipboard
        .writeText(newUrl)
        .then(() => {
            useToastStore().addToast({
                title: "Copy to Clipboard",
                content: "The header URL has been copied to the clipboard",
                type: "success",
            });
        })
        .catch(() => {
            useToastStore().addToast({
                title: "Copy to Clipboard",
                content: "Failed to copy the header URL to the clipboard",
                type: "danger",
            });
        });
};
</script>

<style lang="scss">
.header {
    color: var(--base-color-text) !important;
    text-decoration: none;

    .hash {
        display: none;
        color: var(--primary-color);
        opacity: 75%;
    }

    &:hover .hash {
        display: inline-block;
    }
}
</style>
