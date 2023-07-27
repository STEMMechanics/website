<template>
    <div class="flex flex-items-center justify-center">
        <div :class="['spinner', { small: props.small }]"></div>
    </div>
</template>

<script setup lang="ts">
const props = defineProps({
    small: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>

<style lang="scss">
.spinner {
    width: 12rem;
    height: 12rem;
    border: 2rem solid transparent;
    border-top-color: #00a5f1;
    border-bottom-color: rgba(0, 0, 0, 0.1);
    border-left-color: rgba(0, 0, 0, 0.1);
    border-right-color: rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    animation: spinner-rotation 8s ease-in-out infinite;
    margin-top: 2rem;
    margin-bottom: 2rem;
    flex-shrink: 0;

    &.small {
        width: 2rem;
        height: 2rem;
        border-width: 0.5rem;
        margin: 0 1.5rem 0 1.5rem;
    }
}

@keyframes spinner-rotation {
    0% {
        transform: rotate(0deg);
        border-top-color: #eb3594;
    }
    20% {
        transform: rotate(360deg);
        border-top-color: #00a5f1;
    }
    40% {
        transform: rotate(720deg);
        border-top-color: #39b54a;
    }
    60% {
        transform: rotate(1080deg);
        border-top-color: #f79e1c;
    }
    80% {
        transform: rotate(1440deg);
        border-top-color: #e11e26;
    }
    100% {
        transform: rotate(1800deg);
        border-top-color: #eb3594;
    }
}
</style>
