<template>
    <header>
        <SMNavbar />
    </header>
    <main>
        <router-view v-slot="{ Component }">
            <component :is="Component" />
        </router-view>
    </main>
    <footer>
        <SMFooter />
    </footer>
    <SMToastList />
    <SMDialogList />
</template>

<script setup lang="ts">
import SMNavbar from "../components/SMNavbar.vue";
import SMFooter from "../components/SMFooter.vue";
import SMToastList from "../components/SMToastList.vue";
import SMDialogList from "../components/SMDialog";
</script>

<style lang="scss">
main {
    display: flex;
    flex-direction: column;
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
