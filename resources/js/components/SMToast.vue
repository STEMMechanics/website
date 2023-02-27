<template>
    <div ref="toast" :class="['sm-toast', type]" :style="styles">
        <div class="sm-toast-inner">
            <h3 v-if="title && title.length > 0">
                {{ title }}
            </h3>
            <p>{{ content }}</p>
            <ion-icon name="close-outline" @click="handleClickClose" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useToastStore } from "../store/ToastStore";

const props = defineProps({
    id: {
        type: Number,
        required: true,
    },
    title: {
        type: String,
        default: "",
        required: false,
    },
    type: {
        type: String,
        default: "primary",
        required: false,
    },
    content: {
        type: String,
        required: true,
    },
});

const toastStore = useToastStore();
const toast = ref(null);
let height = 40;
let hideTimeoutID: number | null = null;

const styles = ref({
    opacity: 0,
    marginTop: "40px",
});

const handleClickClose = () => {
    if (hideTimeoutID != null) {
        window.clearTimeout(hideTimeoutID);
        hideTimeoutID = null;
    }
    removeToast();
};

const removeToast = () => {
    styles.value.opacity = 0;
    styles.value.marginTop = `-${height}px`;
    window.setTimeout(() => {
        toastStore.clearToast(props.id);
    }, 500);
};

onMounted(() => {
    window.setTimeout(() => {
        styles.value.opacity = 1;
        styles.value.marginTop = 0;

        if (toast.value != null) {
            const styles = window.getComputedStyle(toast.value);
            const marginBottom = parseFloat(styles.marginBottom);
            height = toast.value.offsetHeight + parseFloat(marginBottom) || 0;
        }

        hideTimeoutID = window.setTimeout(() => {
            hideTimeoutID = null;
            removeToast();
        }, 8000);
    }, 200);
});
</script>

<style lang="scss">
.sm-toast {
    position: relative;
    font-size: 70%;
    background-color: #fff;
    padding: map-get($spacer, 2) map-get($spacer, 2) map-get($spacer, 2)
        map-get($spacer, 2);
    border-radius: 12px;
    border: 1px solid $border-color;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.25);
    margin-bottom: 1rem;
    transition: opacity 0.2s ease-in, margin 0.2s ease-in;

    .sm-toast-inner {
        border-left: 6px solid $primary-color;
        padding: map-get($spacer, 1) map-get($spacer, 4) map-get($spacer, 1)
            map-get($spacer, 2);
        max-width: 250px;
    }

    h3 {
        margin-top: 0;
    }

    p {
        margin-bottom: 0;
        line-height: 1rem;
    }

    ion-icon {
        font-size: 1.25rem;
        position: absolute;
        top: 15px;
        right: 15px;
        color: $font-color;
        cursor: pointer;
        transition: color 0.2s ease-in-out;

        &:hover {
            color: $danger-color;
        }
    }

    &.success .sm-toast-inner {
        border-left-color: $success-color;
    }

    &.danger .sm-toast-inner {
        border-left-color: $danger-color;
    }
}
</style>
