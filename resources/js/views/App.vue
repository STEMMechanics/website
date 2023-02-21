<template>
    <SMNavbar />
    <main>
        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" />
            </transition>
        </router-view>
    </main>
    <SMFooter />
    <SMLoader :loading="applicationStore.routerLoading" />
    <DialogWrapper :transition-attrs="{ name: 'fade' }" />
</template>

<script setup lang="ts">
import SMNavbar from "../components/SMNavbar.vue";
import SMFooter from "../components/SMFooter.vue";
import SMLoader from "../components/SMLoader.vue";
import { DialogWrapper } from "vue3-promise-dialog";
import { useApplicationStore } from "../store/ApplicationStore";

const applicationStore = useApplicationStore();
</script>

<style lang="scss">
main {
    display: flex;
    flex: 1;
}

.fade-enter-active,
.fade-leave-active {
    transition: all 0.35s ease;
}

.fade-enter-from,
.fade-leave-active {
    opacity: 0;
}
</style>
