<template>
    <div class="sm-modal">
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";

onMounted(() => {
    document.getElementsByTagName("body")[0].style.overflow = "hidden";
});

onUnmounted(() => {
    document.getElementsByTagName("body")[0].style.overflow = "auto";
});
</script>
