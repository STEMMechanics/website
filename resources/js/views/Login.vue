<template>
    <SMContainer>
        <SMRow>
            <SMColumn>
                <SMDialog narrow>
                    <h1>Log in</h1>
                    <SMMessage
                        v-if="formMessage.message"
                        :type="formMessage.type"
                        :message="formMessage.message"
                        :icon="formMessage.icon" />
                    <form @submit.prevent="submit">
                        <SMInput
                            v-model:error="formData.username.error"
                            v-model="formData.username.value"
                            name="username"
                            label="Username"
                            required
                            @blur="fieldValidate(formData.username)">
                            <router-link to="/forgot-username"
                                >Forgot username?</router-link
                            >
                        </SMInput>
                        <SMInput
                            v-model="formData.password.value"
                            name="password"
                            type="password"
                            label="Password"
                            required
                            :error="formData.password.error"
                            @blur="fieldValidate(formData.password)">
                            <router-link to="/forgot-password"
                                >Forgot password?</router-link
                            >
                        </SMInput>
                        <SMCaptchaNotice />
                        <SMFormFooter>
                            <template #left>
                                <div>
                                    <span class="pr-1">Need an account?</span
                                    ><router-link to="/register"
                                        >Register</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Log in"
                                    icon="fa-solid fa-arrow-right" />
                            </template>
                        </SMFormFooter>
                    </form>
                </SMDialog>
            </SMColumn>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import SMInput from "../components/SMInput.vue";
import SMButton from "../components/SMButton.vue";
import SMFormFooter from "../components/SMFormFooter.vue";
import SMDialog from "../components/SMDialog.vue";
import SMMessage from "../components/SMMessage.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../helpers/validation";
import { useUserStore } from "../store/UserStore";
import { useRoute, useRouter } from "vue-router";
import SMCaptchaNotice from "../components/SMCaptchaNotice.vue";

const router = useRouter();
const userStore = useUserStore();
const formLoading = ref(false);
const formMessage = reactive({
    message: "",
    type: "error",
    icon: "",
});
const formData = reactive({
    username: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "Your username is needed",
            min: 4,
            min_message: "Your username is at least 6 characters",
        },
    },
    password: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A password is required",
            min: 8,
            min_message: "Your password needs to be at least %d characters",
            password: "special",
            password_message:
                "Your password needs to have at least a letter, a number and a special character",
        },
    },
});

useValidation(formData);

const redirect = useRoute().query.redirect;

const submit = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        if (isValidated(formData)) {
            let res = await axios.post("login", {
                username: formData.username.value,
                password: formData.password.value,
            });

            if (res.data.token !== undefined) {
                userStore.setUserDetails(res.data.user);
                userStore.setUserToken(res.data.token);
                if (redirect !== undefined) {
                    if (redirect.startsWith("api/")) {
                        window.location.href =
                            redirect +
                            "?token=" +
                            encodeURIComponent(res.data.token);
                    } else {
                        router.push({ path: redirect });
                    }
                } else {
                    router.push({ name: "dashboard" });
                }
            } else {
                formMessage.message =
                    "An unexpected error occurred on the server. Please try again later";
            }
        }
    } catch (err) {
        restParseErrors(formData, [formMessage, "message"], err);
    }

    formLoading.value = false;
};
</script>

<style lang="scss"></style>
