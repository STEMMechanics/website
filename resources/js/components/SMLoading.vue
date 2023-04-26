<template>
    <div :class="['loading-background', { overlay: props.overlay }]">
        <div :class="{ 'loading-box': props.overlay, large: props.large }">
            <SMLoadingIcon v-bind="{ large: props.large }" />
            <p v-if="props.text" class="loading-text">{{ props.text }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import SMLoadingIcon from "./SMLoadingIcon.vue";

const props = defineProps({
    large: {
        type: Boolean,
        default: false,
        required: false,
    },
    text: {
        type: String,
        default: "",
        required: false,
    },
    overlay: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>

<style lang="scss">
.loading-background {
    display: flex;
    flex-grow: 1;
    align-items: center;
    justify-content: center;

    &.overlay {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        z-index: 10000;
        background-color: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }

    div {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .loading-box {
        background-color: #fff;
        padding: 24px 48px;
        border-radius: 10px;
        box-shadow: var(--base-shadow);

        .loading-text {
            font-size: 120%;
            margin-top: #{map-get($spacing, 2)};
            margin-bottom: 0;
        }

        &.large .loading-text {
            font-size: 150%;
            margin-top: #{map-get($spacing, 3)};
        }
    }
}
</style>
