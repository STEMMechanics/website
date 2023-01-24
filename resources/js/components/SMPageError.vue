<template v-if="error >= 300 || slots.default">
    <d-error-forbidden v-if="error == 403"></d-error-forbidden>
    <d-error-internal v-if="error >= 500"></d-error-internal>
    <d-error-notfound v-if="error == 404"></d-error-notfound>
    <template v-if="slots.default">
        <slot></slot>
    </template>
</template>

<script setup lang="ts">
import { useSlots } from "vue";
import DErrorForbidden from "./errors/Forbidden.vue";
import DErrorInternal from "./errors/Internal.vue";
import DErrorNotfound from "./errors/Notfound.vue";

defineProps({
    error: {
        type: Number,
        required: true,
        default: 200,
    },
});

const slots = useSlots();
</script>
