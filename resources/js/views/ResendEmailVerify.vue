<template>
    <SMContainer>
        <SMRow>
            <SMDialog narrow :loading="formLoading">
                <template v-if="!formDone">
                    <h1>Resend Verify Email</h1>
                    <SMMessage
                        v-if="formMessage.message"
                        :type="formMessage.type"
                        :message="formMessage.message"
                        :icon="formMessage.icon" />
                    <form @submit.prevent="submit">
                        <SMInput
                            v-model="formData.username.value"
                            name="username"
                            label="Username"
                            required
                            :error="formData.username.error"
                            @blur="fieldValidate(formData.username)" />
                        <SMCaptchaNotice />
                        <SMFormFooter>
                            <template #left>
                                <div>
                                    <span class="pr-1">Stuck?</span
                                    ><router-link to="/contact"
                                        >Contact Us</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Send"
                                    icon="fa-solid fa-arrow-right" />
                            </template>
                        </SMFormFooter>
                    </form>
                </template>
                <template v-else>
                    <h1>Email Sent!</h1>
                    <p class="text-center">
                        If that username has been registered, and you still need
                        to verify your email, you will receive an email with a
                        new verify code.
                    </p>
                    <SMRow class="pb-2">
                        <SMColumn class="justify-content-center">
                            <SMButton :to="{ name: 'home' }" label="Home" />
                        </SMColumn>
                    </SMRow>
                </template>
            </SMDialog>
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
import { useRoute } from "vue-router";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../helpers/validation";
import SMCaptchaNotice from "../components/SMCaptchaNotice.vue";
import { useReCaptcha } from "vue-recaptcha-v3";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formLoading = ref(false);
const formDone = ref(false);
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
            min_message: "Your username is at least %d characters",
        },
    },
});

useValidation(formData);

const submit = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        if (isValidated(formData)) {
            await recaptchaLoaded();
            const captcha = await executeRecaptcha("submit");

            let res = await axios.post("users/resendVerifyEmailCode", {
                username: formData.username.value,
                captcha_token: captcha,
            });

            formDone.value = true;
        }
    } catch (err) {
        if (err.response.status == 422) {
            formDone.value = true;
        } else {
            restParseErrors(formData, [formMessage, "message"], err);
        }
    }

    formLoading.value = false;
};
</script>

<style lang="scss"></style>
