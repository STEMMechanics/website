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
    padding: 32px 32px 16px 32px;
    background-color: var(--tab-color);
    border-width: 0 1px 1px 1px;
    border-style: solid;
    border-color: var(--tab-color-border);
}
</style>
