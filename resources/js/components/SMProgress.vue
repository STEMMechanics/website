<template>
    <div>
        <div
            class="sm-progress-container"
            :style="{ opacity: `${progressStore.opacity || 0}` }">
            <div
                class="sm-progress"
                :style="{
                    width: `${(progressStore.status || 0) * 100}%`,
                }"></div>
        </div>
        <div
            class="sm-spinner"
            :style="{ opacity: `${progressStore.spinner || 0}` }">
            <div class="sm-spinner-icon"></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useProgressStore } from "../store/ProgressStore";

const progressStore = useProgressStore();
</script>

<style lang="scss">
.sm-progress-container {
    position: fixed;
    background-color: $border-color;
    height: 2px;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2000;
    transition: opacity 0.2s ease-in-out;

    .sm-progress {
        background-color: $primary-color-dark;
        width: 0%;
        height: 100%;
        transition: width 0.2s ease-in-out;
        box-shadow: 0 0 10px $primary-color-dark, 0 0 4px $primary-color-dark;
        opacity: 1;
    }
}

.sm-spinner {
    position: fixed;
    top: 10px;
    right: 10px;
    transition: opacity 0.2s ease-in-out;

    .sm-spinner-icon {
        width: 18px;
        height: 18px;
        box-sizing: border-box;

        border: solid 2px transparent;
        border-top-color: #29d;
        border-left-color: #29d;
        border-radius: 50%;

        -webkit-animation: sm-progress-spinner 500ms linear infinite;
        animation: sm-progress-spinner 500ms linear infinite;
    }
}

@-webkit-keyframes sm-progress-spinner {
    0% {
        -webkit-transform: rotate(0deg);
    }
    100% {
        -webkit-transform: rotate(360deg);
    }
}
@keyframes sm-progress-spinner {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>
