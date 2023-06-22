<template>
    <SMNavbar />
    <main class="flex-1">
        <SMLoading v-if="loading" class="h-95" />
        <router-view v-else v-slot="{ Component }">
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
import SMLoading from "../components/SMLoading.vue";
import { useApplicationStore } from "../store/ApplicationStore";
import { ref, watch } from "vue";

const loading = ref(true);
let loadingTimeout = null;

watch(
    () => useApplicationStore().hydrated,
    (newValue) => {
        if (newValue == true) {
            if (loadingTimeout != null) {
                clearTimeout(loadingTimeout);
                loadingTimeout = null;
            }
            loading.value = false;
        } else {
            if (loadingTimeout == null) {
                loadingTimeout = setTimeout(() => {
                    loading.value = true;
                }, 2000);
            }
        }
    }
);
</script>
