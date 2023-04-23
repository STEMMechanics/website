<template>
    <div
        :class="[
            'container',
            { full: full, narrow: narrow, 'my-auto': center },
        ]">
        <slot v-if="slots.default"></slot>
        <div v-if="slots.inner" class="container-inner">
            <slot name="inner"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

defineProps({
    full: {
        type: Boolean,
        default: false,
        required: false,
    },
    narrow: {
        type: Boolean,
        default: false,
        required: false,
    },
    center: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const slots = useSlots();
</script>

<style lang="scss">
.container {
    display: flex;
    width: 100%;
    flex-direction: column;
    padding: 0 16px;
    max-width: 1200px;
    // align-items: center;
    margin: 0 auto;

    &.full {
        padding: 0;
        max-width: 100%;

        .container-inner {
            padding-left: 16px;
            padding-right: 16px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
    }

    &.narrow {
        max-width: 800px;
    }
}
</style>
