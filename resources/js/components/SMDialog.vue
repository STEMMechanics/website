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
                    <SMLoadingIcon />
                    <span>{{ loadingMessage }}</span>
                </div>
            </div>
        </transition>
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import SMLoadingIcon from "./SMLoadingIcon.vue";

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

<style lang="scss">
.dialog {
    flex-direction: column;
    margin: 0 auto;
    flex: 1;
    background-color: #eee;
    padding: map-get($spacer, 5) map-get($spacer, 5)
        calc(map-get($spacer, 5) / 1.5) map-get($spacer, 5);
    border: 1px solid #eee;
    border-radius: 24px;
    overflow: hidden;
    min-width: map-get($spacer, 5) * 12;
    box-shadow: 4px 4px 20px rgba(0, 0, 0, 0.5);

    & > h1 {
        margin-top: 0;
    }

    &.dialog-narrow {
        min-width: auto;
        max-width: map-get($spacer, 5) * 10;
    }

    &.dialog-full {
        width: 100%;
    }

    .dialog-loading-cover {
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

        .dialog-loading {
            display: flex;
            flex-direction: column;
            padding: map-get($spacer, 5) calc(map-get($spacer, 5) * 2);

            border: 1px solid transparent;
            border-radius: 24px;

            svg {
                font-size: calc(map-get($spacer, 5) * 1.5);
            }

            span {
                font-size: map-get($spacer, 4);
                padding-top: map-get($spacer, 3);
            }
        }
    }
}

@media only screen and (max-width: 640px) {
    .dialog {
        padding: map-get($spacer, 1) map-get($spacer, 3) map-get($spacer, 3)
            map-get($spacer, 3);
        min-width: auto;

        .button {
            display: block;
            width: 100%;
            text-align: center;

            margin-top: map-get($spacer, 1);
            margin-bottom: map-get($spacer, 1);
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
    }
}
</style>
