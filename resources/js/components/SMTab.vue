<template>
    <div
        v-show="id == selectedTab"
        class="border-1 border-gray rounded-b-2 rounded-tr-2 p-4">
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import { inject } from "vue";

defineProps({
    label: {
        type: String,
        required: true,
    },
    id: {
        type: String,
        required: true,
    },
});

const selectedTab = inject("selectedTab");
</script>
