<template>
    <a :href="computedUrl" :target="props.target" rel="noopener"
        ><slot></slot
    ></a>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useUserStore } from "../store/UserStore";

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    target: {
        type: String,
        default: "_self",
    },
});

const userStore = useUserStore();

/**
 * Return the URL with a token param attached if the user is logged in and its a api media download request.
 */
const computedUrl = computed(() => {
    const url = new URL(props.href);
    const path = url.pathname;
    const mediumRegex = /^\/media\/[a-zA-Z0-9]+\/download$/;

    if (mediumRegex.test(path) && userStore.token) {
        if (url.search) {
            return `${props.href}&token=${encodeURIComponent(userStore.token)}`;
        } else {
            return `${props.href}?token=${encodeURIComponent(userStore.token)}`;
        }
    }

    return props.href;
});
</script>
