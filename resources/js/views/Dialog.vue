<template>
    <div ref="root" :class="classes">
        <div class="mdialog">
            <button @click="confimer">Close</button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onBeforeMount } from "vue";
import { transitionEnter, transitionLeave } from "../helpers/common";

const root = ref(null);
const classes = ref(["mdialog-mask", "fade-enter-from"]);

const props = defineProps({ title: "" });
let data = {
    title: props.title,
};

const emit = defineEmits(["confirm", "cancel"]);

const confimer = () => {
    transitionLeave(root, "fade", () => {
        emit("confirm", {});
    });
};

transitionEnter(root, "fade");
</script>

<style lang="scss">
.mdialog-mask {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mdialog {
    padding: 1rem 2rem;
    background-color: white;
}
</style>
