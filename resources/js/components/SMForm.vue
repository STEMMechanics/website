<template>
    <form :id="id" @submit.prevent="handleSubmit">
        <slot></slot>
    </form>
</template>

<script setup lang="ts">
import { provide, watch } from "vue";
import { generateRandomElementId } from "../helpers/utils";

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    formId: {
        type: String,
        default: "form",
        required: false,
    },
});

const emits = defineEmits(["submit", "failedValidation"]);
const id = generateRandomElementId();
let inputs = [];

watch(
    () => props.modelValue.loading(),
    (status) => {
        if (!status) {
            enableFormInputs();
        }
    },
);

/**
 * Handle the user submitting the form.
 */
const handleSubmit = async function () {
    inputs = Array.from(document.querySelectorAll(`#${id} input`));

    for (let i = inputs.length - 1; i >= 0; i--) {
        const input = inputs[i] as HTMLInputElement;
        if (!input.disabled) {
            input.disabled = true;
        } else {
            inputs.splice(i, 1);
        }
    }

    if (await props.modelValue.validate()) {
        emits("submit", () => {
            enableFormInputs();
        });
    } else {
        emits("failedValidation");
        enableFormInputs();
    }
};

/**
 * Reenable form inputs
 */
const enableFormInputs = () => {
    for (const input of inputs) {
        const typedInput = input as HTMLInputElement;
        typedInput.disabled = false;
    }

    inputs = [];
};

provide(props.formId, props.modelValue);
</script>
