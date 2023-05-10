<template>
    <form class="form" @submit.prevent="handleSubmit">
        <slot></slot>
    </form>
</template>

<script setup lang="ts">
import { provide } from "vue";

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

/**
 * Handle the user submitting the form.
 */
const handleSubmit = async function () {
    if (await props.modelValue.validate()) {
        emits("submit");
    } else {
        emits("failedValidation");
    }
};

provide(props.formId, props.modelValue);
</script>

<style lang="scss">
.form {
    width: 100%;
}
</style>
