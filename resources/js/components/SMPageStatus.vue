<template>
    <div class="flex flex-items-center justify-center px-8 py-48 text-gray-8">
        <h2 class="border-r border-gray pr-3 mr-3 font-500">
            {{ props.status }}
        </h2>
        <p>{{ statusText }}</p>
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";

const props = defineProps({
    status: {
        type: Number,
        default: 200,
        required: true,
    },
});

const statusText = ref("");

switch (props.status) {
    case 403:
        statusText.value = "You are not permitted to view this page";
        break;
    case 404:
        statusText.value = "This page was not found";
        break;
    case 503:
        statusText.value = "The server is currently under maintenance";
        break;
    default:
        statusText.value = "An unknown error occurred";
        break;
}
</script>
