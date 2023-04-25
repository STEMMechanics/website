<template>
    <div v-show="label == selectedLabel" class="tab-content">
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
});

const selectedLabel = inject("selectedLabel");
</script>

<style lang="scss">
.tab-content {
    padding: 32px;
    background-color: var(--tab-color);
    border: 1px solid var(--tab-color-border);
}
</style>
