<template>
    <div :class="['sm-container', { full: full }]">
        <slot v-if="slots.default"></slot>
        <div v-if="slots.inner" class="sm-container-inner">
            <slot name="inner"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

const props = defineProps({
    full: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const slots = useSlots();
</script>

<style lang="scss">
.sm-container {
    display: flex;
    width: 100%;
    flex-direction: column;
    padding: 0 16px 0 16px;
    margin: auto;
    max-width: 1200px;

    &.full {
        padding: 0;
        max-width: 100%;

        .sm-container-inner {
            padding-left: 1rem;
            padding-right: 1rem;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
    }
}
</style>
