<template>
    <div :class="['loading-icon-balls', { large: props.large }]">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
</template>

<script setup lang="ts">
const props = defineProps({
    large: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>

<style lang="scss">
.loading-icon-balls {
    display: inline-block;
    position: relative;
    width: 2.5em;
    height: 0.5em;

    div {
        position: absolute;
        top: 0em;
        width: 0.5em;
        height: 0.5em;
        border-radius: 50%;
        background: var(--base-color-text);
        animation-timing-function: cubic-bezier(0, 1, 1, 0);
    }
    div:nth-child(1) {
        left: 0em;
        animation: loading-icon1 0.6s infinite;
    }
    div:nth-child(2) {
        left: 0em;
        animation: loading-icon2 0.6s infinite;
    }
    div:nth-child(3) {
        left: 1em;
        animation: loading-icon2 0.6s infinite;
    }
    div:nth-child(4) {
        left: 2em;
        animation: loading-icon3 0.6s infinite;
        z-index: -1;
    }

    @keyframes loading-icon1 {
        0% {
            transform: scale(0);
        }
        100% {
            transform: scale(1);
        }
    }
    @keyframes loading-icon3 {
        0% {
            transform: scale(1);
        }
        100% {
            transform: scale(0);
        }
    }
    @keyframes loading-icon2 {
        0% {
            transform: translate(0, 0);
        }
        100% {
            transform: translate(1em, 0);
        }
    }

    &.large {
        width: 7.5em;
        height: 1.5em;

        div {
            width: 1.5em;
            height: 1.5em;
        }

        div:nth-child(2) {
            animation: loading-large-icon2 0.6s infinite;
        }
        div:nth-child(3) {
            left: 3em;
            animation: loading-large-icon2 0.6s infinite;
        }
        div:nth-child(4) {
            left: 6em;
        }

        @keyframes loading-large-icon2 {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(3em, 0);
            }
        }
    }
}
</style>
