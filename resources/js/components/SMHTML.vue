<template>
    <component :is="computedContent"></component>
</template>

<script setup lang="ts">
import DOMPurify from "dompurify";
import { computed } from "vue";
import { ImportMetaExtras } from "../../../import-meta";

const props = defineProps({
    html: {
        type: String,
        default: "",
        required: true,
    },
});

/**
 * Return the html as a component, relative links as router-link and sanitized.
 */
const computedContent = computed(() => {
    let html = "";

    const regex = new RegExp(
        `<a ([^>]*?)href="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }(.*?>.*?)</a>`,
        "ig"
    );

    html = props.html.replace(regex, '<router-link $1to="$2</router-link>');
    html = DOMPurify.sanitize(html);

    return {
        template: `<div class="sm-content">${html}</div>`,
    };
});
</script>
