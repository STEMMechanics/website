<template>
    <div
        class="fixed top-0 left-0 w-full h-full z-2 bg-black bg-op-20 backdrop-blur"></div>
    <div class="fixed top-0 left-0 w-full flex-justify-center flex z-3 pt-36">
        <div
            class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
            <h1 class="mb-4">{{ props.title }}</h1>
            <p class="mb-4" v-html="props.text"></p>
            <div class="flex flex-justify-between pt-4">
                <button
                    type="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    @click="handleClickCancel()">
                    {{ props.cancel.label }}
                </button>
                <button
                    type="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    @click="handleClickConfirm()">
                    {{ props.confirm.label }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import { closeDialog } from "../SMDialog";
import { useApplicationStore } from "../../store/ApplicationStore";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    text: {
        type: String,
        required: true,
    },
    cancel: {
        type: Object,
        default() {
            return {
                type: "secondary",
                label: "No",
            };
        },
    },
    confirm: {
        type: Object,
        default() {
            return {
                type: "primary",
                label: "Yes",
            };
        },
    },
});

const applicationStore = useApplicationStore();

/**
 * Handle the user clicking the cancel button.
 */
const handleClickCancel = () => {
    closeDialog(false);
};

/**
 * Handle the user clicking the confirm button.
 */
const handleClickConfirm = () => {
    closeDialog(true);
};

/**
 * Handle a keyboard event in this component.
 * @param {KeyboardEvent} event The keyboard event.
 * @returns {boolean} If the event was handled.
 */
const eventKeyUp = (event: KeyboardEvent): boolean => {
    if (event.key === "Escape") {
        handleClickCancel();
        return true;
    } else if (event.key === "Enter") {
        handleClickConfirm();
        return true;
    }

    return false;
};

onMounted(() => {
    applicationStore.addKeyUpListener(eventKeyUp);
});

onUnmounted(() => {
    applicationStore.removeKeyUpListener(eventKeyUp);
});
</script>
