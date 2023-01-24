<template>
    <SMContainer>
        <SMRow>
            <SMDialog narrow :loading="formLoading">
                <template v-if="!formDone">
                    <h1>Unsubscribe</h1>
                    <p>
                        If you would like to unsubscribe from our mailing list,
                        you have come to the right page!
                    </p>
                    <SMMessage
                        v-if="formMessage.message"
                        :type="formMessage.type"
                        :message="formMessage.message"
                        :icon="formMessage.icon" />
                    <form @submit.prevent="submit">
                        <SMInput
                            v-model="formData.email.value"
                            name="email"
                            label="Email"
                            required
                            :error="formData.email.error"
                            @blur="fieldValidate(formData.email)" />
                        <SMCaptchaNotice />
                        <SMFormFooter>
                            <template #right>
                                <SMButton type="submit" label="Unsubscribe" />
                            </template>
                        </SMFormFooter>
                    </form>
                </template>
                <template v-else>
                    <h1>Unsubscribed</h1>
                    <p class="text-center">
                        You have now been unsubscribed from our newsletter.
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
    email: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "An email address is required.",
            email: true,
            email_message: "That does not look like an email address.",
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

            await axios.delete("subscriptions", {
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

if (useRoute().query.email !== undefined) {
    formData.email.value = useRoute().query.email;
    submit();
}
</script>
