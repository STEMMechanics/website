<template>
    <template v-if="loading">
        <transition name="fade">
            <div v-if="loading" class="sm-loader">
                <SMLoadingIconToolbox v-if="type.toLowerCase() == 'toolbox'" />
                <SMLoadingIconBalls v-else />
            </div>
        </transition>
    </template>
    <slot v-else></slot>
</template>

<script setup lang="ts">
import SMLoadingIconToolbox from "./SMLoadingIconToolbox.vue";
import SMLoadingIconBalls from "./SMLoadingIcon.vue";

defineProps({
    loading: {
        type: Boolean,
        default: false,
        required: true,
    },
    type: {
        type: String,
        default: "",
        required: false,
    },
});
</script>

<style lang="scss">
.sm-loader {
    position: fixed;
    display: flex;
    justify-content: center;
    align-items: center;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(4px);
    background-color: rgba(255, 255, 255, 0.5);
    z-index: 10000;
}
</style>
