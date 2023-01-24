<template>
    <div :class="['message', type]">
        <font-awesome-icon v-if="icon" :icon="icon" />{{ message }}
    </div>
</template>

<script setup lang="ts">
defineProps({
    message: {
        type: String,
        default: "",
    },
    type: {
        type: String,
        default: "primary",
    },
    icon: {
        type: String,
        default: "",
    },
});
</script>

<style lang="scss">
.message {
    padding: map-get($spacer, 2) map-get($spacer, 3);
    margin-bottom: map-get($spacer, 4);
    text-align: center;
    font-size: 90%;
    word-break: break-word;

    svg {
        padding-right: map-get($spacer, 1);
    }

    &.primary {
        background-color: $primary-color-lighter;
        color: $primary-color-darker;
        border: 1px solid $primary-color-lighter;
        border-radius: 12px;
    }

    &.success {
        background-color: $success-color-lighter;
        color: $success-color-darker;
        border: 1px solid $success-color-lighter;
        border-radius: 12px;
    }

    &.error {
        background-color: $danger-color-lighter;
        color: $danger-color-darker;
        border: 1px solid $danger-color-lighter;
        border-radius: 12px;
    }
}
</style>
