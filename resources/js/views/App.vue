<template>
    <SMNavbar />
    <main class="flex-1">
        <router-view v-slot="{ Component }">
            <component :is="Component" />
        </router-view>
    </main>
    <SMPageFooter />
    <SMToastList />
    <SMDialogList />
</template>

<script setup lang="ts">
import SMNavbar from "../components/SMNavbar.vue";
import SMPageFooter from "../components/SMPageFooter.vue";
import SMToastList from "../components/SMToastList.vue";
import SMDialogList from "../components/SMDialog";
</script>
