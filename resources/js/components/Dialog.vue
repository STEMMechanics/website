<template>
    <transition name="fade" mode="out-in">
        <div :class="['mdialog-mask', classes]">
            <div class="mdialog">
                <h3 v-if="title">{{ title }}</h3>
                <component :is="component" v-if="component" />
                <template v-else-if="content">
                    <div v-html="content" />
                </template>
                <button
                    @click="
                        show = false;
                        onClose();
                    ">
                    Cancel
                </button>
            </div>
        </div>
    </transition>
</template>

<script setup lang="ts">
import { ref, defineExpose, shallowRef } from "vue";

const show = ref(false);
const title = ref("");
const content = ref("");
const onClose = ref(() => {});
const classes = ref("");
const component = shallowRef("");

defineExpose({
    title,
    content,
    component,
    show,
    onClose,
});
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
