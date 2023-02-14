<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <SMRow>
            <SMDialog narrow class="mt-5" :loading="formLoading">
                <h1>Logged out</h1>
                <SMRow>
                    <SMColumn class="justify-content-center">
                        <p class="mt-0 text-center">
                            You have now been logged out
                        </p>
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn class="justify-content-center">
                        <SMButton :to="{ name: 'home' }" label="Home" />
                    </SMColumn>
                </SMRow>
            </SMDialog>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { api } from "../helpers/api";
import { ref } from "vue";
import { useUserStore } from "../store/UserStore";

import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMPage from "../components/SMPage.vue";

const userStore = useUserStore();
const formLoading = ref(false);

const logout = async () => {
    formLoading.value = true;

    try {
        await api.post({
            url: "/logout",
        });
    } catch (err) {
        console.log(err);
    }

    userStore.clearUser();
    formLoading.value = false;
};

logout();
</script>

<style lang="scss"></style>
