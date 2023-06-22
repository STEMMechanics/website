<template>
    <div
        ref="toast"
        class="border-1 border-gray-2 bg-white rounded-md p-4 mt-4 mb-4"
        :style="styles">
        <div :class="['max-w-48', 'border-l-5', 'pl-4', 'relative', colour]">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-4 absolute right-0 hover:text-red-7 cursor-pointer"
                @click="handleClickClose">
                <path
                    d="m249-207-42-42 231-231-231-231 42-42 231 231 231-231 42 42-231 231 231 231-42 42-231-231-231 231Z"
                    fill="currentColor" />
            </svg>
            <h5 class="mt-0 mb-2" v-if="title && title.length > 0">
                {{ title }}
            </h5>
            <p class="text-xs">{{ content }}</p>
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
let colour = "";
let height = 40;
let hideTimeoutID: number | null = null;

const styles = ref({
    transition: "opacity 0.2s ease-in, margin 0.2s ease-in",
    opacity: 0,
    marginTop: "40px",
});

switch (props.type) {
    case "primary":
        colour = "border-sky-5";
        break;
    case "danger":
        colour = "border-red-7";
        break;
    case "success":
        colour = "border-green-7";
        break;
    case "warning":
        colour = "border-yellow-4";
        break;
}

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
        styles.value.marginTop = "0";

        if (toast.value != null) {
            const styles = window.getComputedStyle(toast.value);
            const marginBottom = parseFloat(styles.marginBottom);
            height = toast.value.offsetHeight + marginBottom || 0;
        }

        hideTimeoutID = window.setTimeout(() => {
            hideTimeoutID = null;
            removeToast();
        }, 8000);
    }, 200);
});
</script>

<!-- <style lang="scss">
.toast {
    position: relative;
    font-size: 80%;
    background-color: var(--base-color-light);
    padding: 16px;
    border-radius: 12px;
    border: 1px solid var(--base-color-border);
    box-shadow: var(--base-shadow);
    margin-bottom: 32px;
    transition: opacity 0.2s ease-in, margin 0.2s ease-in;

    .toast-inner {
        border-left: 6px solid var(--primary-color);
        padding: 8px 32px 8px 16px;
        max-width: 250px;
    }

    .title {
        margin-top: 0 !important;
    }

    p {
        margin-bottom: 0;
        word-wrap: break-word;
    }

    ion-icon {
        font-size: 150%;
        position: absolute;
        top: 15px;
        right: 15px;
        color: var(--base-color-text);
        cursor: pointer;
        transition: color 0.1s linear;

        &:hover {
            color: var(--danger-color);
        }
    }

    &.success .toast-inner {
        border-left-color: var(--success-color);
    }

    &.danger .toast-inner {
        border-left-color: var(--danger-color);
    }
}
</style> -->
