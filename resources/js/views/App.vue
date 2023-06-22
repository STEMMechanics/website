<template>
    <SMNavbar />
    <main class="flex-1">
        <SMLoading v-if="!appLoaded" class="h-95" />
        <router-view v-slot="{ Component }">
            <component :is="Component" />
        </router-view>
    </main>
    <SMPageFooter />
    <SMToastList />
    <SMDialogList />
</template>

<script setup lang="ts">
const appLoaded = ref(false);

import SMNavbar from "../components/SMNavbar.vue";
import SMPageFooter from "../components/SMPageFooter.vue";
import SMToastList from "../components/SMToastList.vue";
import SMDialogList from "../components/SMDialog";
import SMLoading from "../components/SMLoading.vue";
import { onMounted, ref } from "vue";

onMounted(() => {
    appLoaded.value = true;
});
</script>
