<template>
    <div v-show="id == selectedTab" class="tab-content">
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

<style lang="scss">
.tab-content {
    padding: 16px 32px 16px 32px;
    background-color: var(--tab-color);
    border-width: 0 1px 1px 1px;
    border-style: solid;
    border-color: var(--tab-color-border);
}
</style>
