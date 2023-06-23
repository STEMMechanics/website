<template>
    <div
        class="fixed top-10 right-10 z-10 overflow-hidden pointer-events-none"
        v-if="toastStore.toasts">
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
