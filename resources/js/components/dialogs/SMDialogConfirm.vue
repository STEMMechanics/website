<template>
    <SMModal>
        <SMFormCard>
            <h1>{{ props.title }}</h1>
            <p v-html="computedSanitizedText"></p>
            <SMFormFooter>
                <template #left>
                    <SMButton
                        :type="props.cancel.type"
                        :label="props.cancel.label"
                        @click="handleClickCancel()" />
                </template>
                <template #right>
                    <SMButton
                        :type="props.confirm.type"
                        :label="props.confirm.label"
                        @click="handleClickConfirm()" />
                </template>
            </SMFormFooter>
        </SMFormCard>
    </SMModal>
</template>

<script setup lang="ts">
import DOMPurify from "dompurify";
import { computed, onMounted, onUnmounted } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import { useApplicationStore } from "../../store/ApplicationStore";
import SMButton from "../SMButton.vue";
import SMFormCard from "../SMFormCard.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMModal from "../SMModal.vue";

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
 * Sanitize the text property from XSS attacks.
 */
const computedSanitizedText = computed(() => {
    return DOMPurify.sanitize(props.text);
});

/**
 * Handle a keyboard event in this component.
 *
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
