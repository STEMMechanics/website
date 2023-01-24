<template>
    <div class="modal">
        <div class="dialog dialog-narrow">
            <h1>{{ props.title }}</h1>
            <p v-html="sanitizedHtml"></p>
            <SMFormFooter>
                <template #left>
                    <SMButton
                        :type="props.cancel.type"
                        :label="props.cancel.label"
                        @click.stop="handleCancel()" />
                </template>
                <template #right>
                    <SMButton
                        :type="props.confirm.type"
                        :label="props.confirm.label"
                        @click.stop="handleConfirm()" />
                </template>
            </SMFormFooter>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import SMButton from "../SMButton.vue";
import SMFormFooter from "../SMFormFooter.vue";
// import sanitizeHtml from "sanitize-html";

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

const handleCancel = () => {
    closeDialog(false);
};

const handleConfirm = () => {
    closeDialog(true);
};

const eventKeyUp = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        handleCancel();
    } else if (event.key === "Enter") {
        handleConfirm();
    }
};

onMounted(() => {
    document.addEventListener("keyup", eventKeyUp);
});

onUnmounted(() => {
    document.removeEventListener("keyup", eventKeyUp);
});

// const sanitizedHtml = sanitizeHtml(props.text);
const sanitizedHtml = props.text;
</script>
