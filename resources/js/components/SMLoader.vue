<template>
    <template v-if="loading">
        <transition name="fade">
            <div v-if="loading" class="loader-cover">
                <div class="loader">
                    <font-awesome-icon icon="fa-solid fa-spinner" pulse />
                </div>
            </div>
        </transition>
    </template>
    <slot v-else></slot>
</template>

<script setup lang="ts">
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>
