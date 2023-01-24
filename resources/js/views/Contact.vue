<template>
    <SMContainer>
        <SMRow>
            <SMColumn>
                <SMDialog narrow :loading="formLoading">
                    <template v-if="!formDone">
                        <h1>Contact Us</h1>
                        <SMMessage
                            v-if="formMessage.message"
                            :type="formMessage.type"
                            :message="formMessage.message"
                            :icon="formMessage.icon" />
                        <form @submit.prevent="submit">
                            <SMInput
                                v-model="formData.name.value"
                                name="name"
                                label="Name"
                                required
                                :error="formData.name.error"
                                @blur="fieldValidate(formData.name)" />
                            <SMInput
                                v-model="formData.email.value"
                                name="email"
                                label="Email"
                                required
                                :error="formData.email.error"
                                @blur="fieldValidate(formData.email)" />
                            <SMInput
                                v-model="formData.content.value"
                                name="content"
                                type="textarea"
                                label="Message"
                                required
                                :error="formData.content.error"
                                @blur="fieldValidate(formData.content)" />
                            <SMCaptchaNotice />
                            <SMRow class="pb-2">
                                <SMColumn class="justify-content-center">
                                    <SMButton
                                        type="submit"
                                        label="Send Message"
                                        icon="fa-regular fa-paper-plane" />
                                </SMColumn>
                            </SMRow>
                        </form>
                    </template>
                    <template v-else>
                        <h1>Message Sent!</h1>
                        <p class="text-center">
                            Your message as been sent to us. We will respond as
                            soon as we can.
                        </p>
                        <SMRow class="pb-2">
                            <SMColumn class="justify-content-center">
                                <SMButton to="/" label="Home" />
                            </SMColumn>
                        </SMRow>
                    </template>
                </SMDialog>
            </SMColumn>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import SMInput from "../components/SMInput.vue";
import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMMessage from "../components/SMMessage.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../helpers/validation";
import { useReCaptcha } from "vue-recaptcha-v3";
import SMCaptchaNotice from "../components/SMCaptchaNotice.vue";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formLoading = ref(false);
const formDone = ref(false);
const formMessage = reactive({
    message: "",
    type: "error",
    icon: "",
});
const formData = reactive({
    name: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A name is needed",
            min: 4,
            min_message: "A name needs to be is at least 4 characters",
        },
    },
    email: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A email address is needed",
            email: true,
            email_message: "That email address does not look right",
        },
    },
    content: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A message is required",
            min: 8,
            min_message: "The message needs to be at least %d characters",
        },
    },
});

useValidation(formData);

const submit = async () => {
    formLoading.value = true;

    try {
        if (isValidated(formData)) {
            await recaptchaLoaded();
            const captcha = await executeRecaptcha("submit");

            let res = await axios.post("contact", {
                name: formData.name.value,
                email: formData.email.value,
                captcha_token: captcha,
                content: formData.content.value,
            });

            formDone.value = true;
        }
    } catch (err) {
        formLoading.value = false;
        formMessage.type = "error";
        formMessage.icon = "fa-solid fa-circle-exclamation";
        restParseErrors(formData, [formMessage, "message"], err);
    }

    formLoading.value = false;
};
</script>
