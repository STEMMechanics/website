<template>
    <div class="loading-container">
        <SMLoadingIcon v-bind="{ large: props.large }" />
    </div>
</template>

<script setup lang="ts">
import SMLoadingIcon from "./SMLoadingIcon.vue";

const props = defineProps({
    large: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>

<style lang="scss">
.loading-container {
    display: flex;
    flex-grow: 1;
    align-items: center;
    justify-content: center;
}
</style>
