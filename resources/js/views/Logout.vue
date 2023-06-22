<template>
    <SMLoading />
</template>

<script setup lang="ts">
import { useRouter } from "vue-router";
import SMLoading from "../components/SMLoading.vue";
import { api } from "../helpers/api";
import { useToastStore } from "../store/ToastStore";
import { useUserStore } from "../store/UserStore";

const router = useRouter();
const userStore = useUserStore();
const toastStore = useToastStore();

/**
 * Logout the current user and redirect to home page.
 */
const logout = async () => {
    api.post({
        url: "/logout",
    }).finally(() => {
        userStore.clearUser();
        toastStore.addToast({
            title: "Logged Out",
            content: "You have been logged out.",
            type: "success",
        });

        router.push({ name: "home" });
    });
};

logout();
</script>

<style lang="scss"></style>
