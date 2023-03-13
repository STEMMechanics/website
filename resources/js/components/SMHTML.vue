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

    // Convert local links to router-links
    const regexHref = new RegExp(
        `<a ([^>]*?)href="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }(.*?>.*?)</a>`,
        "ig"
    );
    html = props.html.replace(regexHref, '<router-link $1to="$2</router-link>');

    // Update local images to use at most the large size
    const regexImg = new RegExp(
        `<img ([^>]*?)src="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }/uploads/([^"]*?)"`,
        "ig"
    );
    html = html.replace(
        regexImg,
        `<img $1src="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }/uploads/$2?size=large"`
    );

    // Sanitize HTML
    html = DOMPurify.sanitize(html);

    return {
        template: `<div class="sm-content">${html}</div>`,
    };
});
</script>
