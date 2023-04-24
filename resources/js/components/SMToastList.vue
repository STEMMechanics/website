<template>
    <div class="toast-container">
        <SMToast
            v-for="toast of toastStore.toasts"
            :id="toast.id"
            :key="toast.id"
            :type="toast.type"
            :title="toast.title"
            :content="toast.content" />
    </div>
</template>

<script setup lang="ts">
import { useToastStore } from "../store/ToastStore";
import SMToast from "./SMToast.vue";

const toastStore = useToastStore();
</script>

<style lang="scss">
.toast-container {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 3000;
    padding: 10px;
    overflow: hidden;
}
</style>
