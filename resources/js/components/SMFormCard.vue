<template>
    <div :class="['form-card', { full: full }]">
        <div v-if="slots.header" class="header">
            <slot name="header"></slot>
        </div>
        <div v-if="slots.body || slots.default" class="body">
            <slot name="body"></slot>
            <slot></slot>
        </div>
        <div v-if="slots.footer" class="footer">
            <slot name="footer"></slot>
        </div>
        <div
            v-if="slots['footer-space-between']"
            class="footer justify-content-space-between">
            <slot name="footer-space-between"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

defineProps({
    narrow: {
        type: Boolean,
        default: false,
    },
    full: {
        type: Boolean,
        default: false,
    },
});

const slots = useSlots();
</script>

<style lang="scss">
.form-card {
    max-width: 640px;
    margin: 64px auto;
    padding: 32px 48px;
    background-color: var(--base-color-light);
    border-radius: 16px;
    box-shadow: var(--base-shadow);

    &.full {
        max-width: 960px;
    }

    .body .row {
        margin: 0 auto;
    }

    .footer {
        margin-top: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        margin-top: 0;
    }
}
</style>
