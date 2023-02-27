<template>
    <form @submit.prevent="handleSubmit">
        <SMLoader :loading="props.modelValue._loading"></SMLoader>
        <SMMessage
            v-if="props.modelValue._message.length > 0"
            :message="props.modelValue._message"
            :type="props.modelValue._messageType"
            :icon="props.modelValue._messageIcon" />

        <slot></slot>
    </form>
</template>

<script setup lang="ts">
import { provide } from "vue";
import SMLoader from "../components/SMLoader.vue";
import SMMessage from "./SMMessage.vue";

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});
const emits = defineEmits(["submit"]);

/**
 * Handle the user submitting the form.
 */
const handleSubmit = function () {
    if (props.modelValue.validate()) {
        emits("submit");
    }
};

provide("form", props.modelValue);
</script>
