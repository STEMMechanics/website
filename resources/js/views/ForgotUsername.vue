<template>
    <SMContainer>
        <SMRow>
            <SMDialog narrow :loading="formLoading">
                <template v-if="!formDone">
                    <h1>Forgot Username</h1>
                    <SMMessage
                        v-if="formMessage.message"
                        :type="formMessage.type"
                        :message="formMessage.message"
                        :icon="formMessage.icon" />
                    <form @submit.prevent="submit">
                        <SMInput
                            v-model:error="formData.email.error"
                            v-model="formData.email.value"
                            name="email"
                            label="Email"
                            required
                            @blur="fieldValidate(formData.email)" />
                        <SMCaptchaNotice />
                        <SMFormFooter>
                            <template #left>
                                <div>
                                    <span class="pr-1">Remember?</span
                                    ><router-link :to="{ name: 'login' }"
                                        >Log in</router-link
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
                        If that email has a registered account, you should
                        receive it shortly.
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
    email: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "An email address is required",
            email: true,
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

            let res = await axios.post("users/forgotUsername", {
                email: formData.email.value,
                captcha_token: captcha,
            });

            formDone.value = true;
        }
    } catch (err) {
        restParseErrors(formData, [formMessage, "message"], err);
    }

    formLoading.value = false;
};
</script>

<style lang="scss"></style>
