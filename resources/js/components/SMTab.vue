<template>
    <div v-show="label == selectedLabel" class="sm-tab-content">
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
.sm-tab-content {
    padding: map-get($spacer, 3);
    background-color: #fff;
    border: 1px solid $border-color;
}
</style>
