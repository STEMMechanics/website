<template>
    <SMContainer>
        <SMRow>
            <SMDialog narrow :loading="formLoading">
                <template v-if="!formDone">
                    <h1>Reset Password</h1>
                    <SMMessage
                        v-if="formMessage.message"
                        :type="formMessage.type"
                        :message="formMessage.message"
                        :icon="formMessage.icon" />
                    <form @submit.prevent="submit">
                        <SMInput
                            v-model="formData.code.value"
                            name="code"
                            label="Reset Code"
                            required
                            :error="formData.code.error"
                            @blur="fieldValidate(formData.code)" />
                        <SMInput
                            v-model="formData.password.value"
                            type="password"
                            name="password"
                            label="New Password"
                            required
                            :error="formData.password.error"
                            @blur="fieldValidate(formData.password)" />
                        <SMCaptchaNotice />
                        <SMFormFooter>
                            <template #left>
                                <div>
                                    <router-link
                                        :to="{ name: 'forgot-password' }"
                                        >Resend Code</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Reset Password"
                                    icon="fa-solid fa-arrow-right" />
                            </template>
                        </SMFormFooter>
                    </form>
                </template>
                <template v-else>
                    <h1>Password Reset!</h1>
                    <p class="text-center">
                        Hurrah, Your password has been changed!
                    </p>
                    <SMRow class="pb-2">
                        <SMColumn class="justify-content-center">
                            <SMButton :to="{ name: 'login' }" label="Login" />
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
    code: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "The code is needed",
            min: 6,
            min_message: "The code should be 6 characters",
            max: 6,
            max_message: "The code should be 6 characters",
        },
    },
    password: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A new password is required",
            min: 8,
            min_message: "Your password needs to be at least %d characters",
            password: "special",
            password_message:
                "Your password needs to have at least a letter, a number and a special character",
        },
    },
});

useValidation(formData);

if (useRoute().query.code !== undefined) {
    formData.code.value = useRoute().query.code;
}

const submit = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        if (isValidated(formData)) {
            await recaptchaLoaded();
            const captcha = await executeRecaptcha("submit");

            let res = await axios.post("users/resetPassword", {
                code: formData.code.value,
                password: formData.password.value,
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
