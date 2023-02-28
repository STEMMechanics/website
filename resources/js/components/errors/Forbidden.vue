<template>
    <SMPage no-breadcrumbs>
        <div class="sm-page-error sm-error-not-found">
            <div class="sm-error-number">
                4<img src="/img/sad-monster.png" />3
            </div>
            <div class="sm-error-content">
                <h2>Ooops!</h2>
                <p>This page is not for you to view!</p>
                <SMButton label="Go Back" @click="handleClick" />
            </div>
        </div>
    </SMPage>
</template>

<script setup lang="ts">
import SMButton from "../SMButton.vue";
import { useRouter } from "vue-router";
import { useApplicationStore } from "../../store/ApplicationStore";

const router = useRouter();
const applicationStore = useApplicationStore();

applicationStore.setDynamicTitle("Forbidden");

/**
 * Handle user clicking back/home button
 */
const handleClick = () => {
    router.go(-1);
};
</script>
