<template>
    <div class="sm-toast-container">
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
.sm-toast-container {
    position: fixed;
    height: 2px;
    top: 4rem;
    right: 1rem;
    height: 100%;
    z-index: 3000;
    overflow: hidden;
}
</style>
