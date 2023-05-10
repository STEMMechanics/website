<template>
    <div class="form-error" v-if="props.modelValue._message.length > 0">
        <ion-icon class="invalid-icon" name="alert-circle-outline"></ion-icon>
        <p>{{ props.modelValue._message }}</p>
    </div>
</template>

<script setup lang="ts">
const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});
</script>

<style lang="scss">
.form-error {
    display: flex;
    color: var(--danger-color);
    background-color: var(--danger-color-lighter);
    padding: 6px 12px;
    margin-bottom: 24px;
    align-items: center;

    ion-icon {
        font-size: 1.5rem;
    }

    p {
        display: inline-block;
        margin: 0;
        text-align: center;
        flex-grow: 1;
        white-space: pre;
    }
}
</style>
