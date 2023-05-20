<template>
    <header>
        <SMNavbar />
    </header>
    <main>
        <SMPage v-if="useApplicationStore().unavailable" :page-error="503" />
        <template v-else>
            <router-view v-slot="{ Component }">
                <component :is="Component" />
            </router-view>
        </template>
    </main>
    <footer>
        <SMPageFooter />
    </footer>
    <div v-if="!useApplicationStore().unavailable" id="sm-page-loading">
        <SMLoadingIcon large />
    </div>
    <SMToastList />
    <SMDialogList />
</template>

<script setup lang="ts">
import SMNavbar from "../components/SMNavbar.vue";
import SMPageFooter from "../components/SMPageFooter.vue";
import SMToastList from "../components/SMToastList.vue";
import SMDialogList from "../components/SMDialog";
import SMLoadingIcon from "../components/SMLoadingIcon.vue";
import { useApplicationStore } from "../store/ApplicationStore";
</script>

<style lang="scss">
main {
    display: flex;
    flex-direction: column;
    flex: 1;
}

#sm-page-loading {
    position: fixed;
    display: flex;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    z-index: 10000;

    .loading-icon-balls div {
        box-shadow: 0 0 2px 2px white;
    }
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
