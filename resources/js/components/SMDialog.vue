<template>
    <div
        :class="[
            'dialog',
            { 'dialog-narrow': narrow },
            { 'dialog-full': full },
        ]">
        <transition name="fade">
            <div v-if="loading" class="dialog-loading-cover">
                <div class="dialog-loading">
                    <font-awesome-icon icon="fa-solid fa-spinner" pulse />
                    <span>{{ loadingMessage }}</span>
                </div>
            </div>
        </transition>
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    loadingMessage: {
        type: String,
        default: "",
    },
    narrow: {
        type: Boolean,
        default: false,
    },
    full: {
        type: Boolean,
        default: false,
    },
});
</script>
