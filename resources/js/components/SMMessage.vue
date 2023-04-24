<template>
    <div class="message-container">
        <div :class="['message', type]">
            <ion-icon v-if="icon" :name="icon"></ion-icon>
            <p>{{ message }}</p>
        </div>
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
.message-container {
    justify-content: center;
    align-self: center;

    .message {
        display: flex;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        margin-bottom: map-get($spacer, 4);
        text-align: center;
        font-size: 90%;
        word-break: break-word;

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

        ion-icon {
            height: 1.3em;
            width: 1.3em;
            margin-right: map-get($spacer, 1);
        }

        p {
            margin-bottom: 0;
            justify-content: center;
            align-self: center;
            white-space: pre-wrap;
            flex: 1;
        }
    }
}
</style>
