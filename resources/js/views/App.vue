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
    <SMProgress />
    <SMToastList />
    <DialogWrapper :transition-attrs="{ name: 'fade' }" />
</template>

<script setup lang="ts">
import SMNavbar from "../components/SMNavbar.vue";
import SMFooter from "../components/SMFooter.vue";
import SMProgress from "../components/SMProgress.vue";
import SMToastList from "../components/SMToastList.vue";
import { DialogWrapper } from "vue3-promise-dialog";
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
