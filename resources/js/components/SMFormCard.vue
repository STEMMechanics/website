<template>
    <div :class="['form-card', { 'form-card-full': full }]">
        <div v-if="slots.header" class="form-card-header">
            <slot name="header"></slot>
        </div>
        <div v-if="slots.body || slots.default``" class="form-card-body">
            <slot name="body"></slot>
            <slot></slot>
        </div>
        <div v-if="slots.footer" class="form-card-footer">
            <slot name="footer"></slot>
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

    .form-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
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
