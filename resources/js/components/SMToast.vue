<template>
    <div
        ref="toast"
        class="border-1 border-gray-2 bg-white rounded-md p-4 mt-4 mb-4 pointer-events-auto"
        :style="styles">
        <div :class="['max-w-48', 'border-l-5', 'pl-4', 'relative', colour]">
            <svg
                v-if="!props.loader"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-4 absolute right-0 hover:text-red-7 cursor-pointer"
                @click="handleClickClose">
                <path
                    d="m249-207-42-42 231-231-231-231 42-42 231 231 231-231 42 42-231 231 231 231-42 42-231-231-231 231Z"
                    fill="currentColor" />
            </svg>
            <h5 class="mt-0 mb-2 pr-6" v-if="title && title.length > 0">
                {{ title }}
            </h5>
            <div class="flex">
                <svg
                    v-if="props.loader"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    class="spin h-4 color-gray mr-2 flex-align-middle">
                    <path
                        d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z"
                        fill="currentColor" />
                </svg>
                <p class="text-xs">
                    {{ content }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useToastStore } from "../store/ToastStore";
import { I } from "vitest/dist/types-198fd1d9";

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
    loader: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const toastStore = useToastStore();
const toast = ref(null);
let height = 40;
let hideTimeoutID: number | null = null;

const styles = ref({
    transition: "opacity 0.2s ease-in, margin 0.2s ease-in",
    opacity: 0,
    marginTop: "40px",
});

let colour = computed(() => {
    switch (props.type) {
        case "danger":
            return "border-red-7";
        case "success":
            return "border-green-7";
        case "warning":
            return "border-yellow-4";
    }

    return "border-sky-5";
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

const cancelRemoveCountdown = () => {
    if (hideTimeoutID != null) {
        window.clearTimeout(hideTimeoutID);
        hideTimeoutID = null;
    }
};

const startRemoveCountdown = () => {
    if (hideTimeoutID == null) {
        hideTimeoutID = window.setTimeout(() => {
            hideTimeoutID = null;
            removeToast();
        }, 8000);
    }
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

        if (!props.loader) {
            startRemoveCountdown();
        }
    }, 200);
});

watch(
    () => props.loader,
    (newValue) => {
        if (newValue) {
            cancelRemoveCountdown();
        } else {
            startRemoveCountdown();
        }
    }
);
</script>
