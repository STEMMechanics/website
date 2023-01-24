<template>
    <SMContainer>
        <SMRow>
            <SMDialog narrow>
                <h1>Logged out</h1>
                <SMRow>
                    <SMColumn class="justify-content-center">
                        <p class="mt-0">You have now been logged out</p>
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn class="justify-content-center">
                        <SMButton :to="{ name: 'home' }" label="Home" />
                    </SMColumn>
                </SMRow>
            </SMDialog>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import SMButton from "@/components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMMessage from "../components/SMMessage.vue";
import axios from "axios";
import { useUserStore } from "../store/UserStore";
import { useRouter } from "vue-router";

const router = useRouter();
const userStore = useUserStore();
const formLoading = ref(false);
const formMessage = reactive({
    type: "info",
    message: "Logging you out...",
    icon: "",
});

formLoading.value = true;

const logout = async () => {
    formLoading.value = true;
    try {
        await axios.post("logout");
    } catch (err) {
        console.log(err);
    }

    userStore.clearUser();
    formLoading.value = false;
};

logout();
</script>

<style lang="scss"></style>
