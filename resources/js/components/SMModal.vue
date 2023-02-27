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

<style lang="scss">
.sm-modal {
    position: fixed;
    display: flex;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    z-index: 1000;
    padding: 1rem;
}
</style>
