<template>
    <component :is="parsedContent"></component>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps({
    html: {
        type: String,
        default: "",
        required: true,
    },
});

const parsedContent = computed(() => {
    let html = "";

    const regex = new RegExp(
        `<a ([^>]*?)href="${import.meta.env.APP_URL}(.*?>.*?)</a>`,
        "ig"
    );
    html = props.html.replace(regex, '<router-link $1to="$2</router-link>');

    return {
        template: `<div class="content">${html}</div>`,
    };
});
</script>
