<template>
    <SMContainer class="page-contact">
        <SMRow break-large>
            <SMColumn>
                <h1 class="text-left">Contact Us</h1>
                <h2>Questions & Support</h2>
                <p>
                    If you have a question or would like help with a project,
                    you can send it our way using the form on this page or be
                    emailing
                    <a href="mailto:hello@stemmechanics.com.au"
                        >hello@stemmechanics.com.au</a
                    >.
                </p>
                <h2>Wanting a workshop?</h2>
                <p>
                    We provide both public and private workshops as well as run
                    events on behalf of your organisation. If you would like to
                    discuss a potential opportunity, send us an email at
                    <a href="mailto:hello@stemmechanics.com.au"
                        >hello@stemmechanics.com.au</a
                    >.
                </p>
                <h2>Address</h2>
                <p>
                    We do not have a physical address as our workshops are
                    delivered across Queensland. Visit the
                    <router-link :to="{ name: 'workshop-list' }"
                        >workshops</router-link
                    >
                    page for each specific location.
                </p>
                <p>Official mail can be sent to the following address:</p>
                <div class="text-center">
                    <p class="font-size-90">
                        STEMMechanics<br />PO Box 36<br />Edmonton, QLD, 4869<br />Australia
                    </p>
                    <p class="font-size-90">
                        <strong>ABN: </strong>15 772 281 735
                    </p>
                </div>
            </SMColumn>
            <SMColumn>
                <div>
                    <SMDialog narrow :loading="formLoading">
                        <template v-if="!formDone">
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
                                Your message as been sent to us. We will respond
                                as soon as we can.
                            </p>
                            <SMRow class="pb-2">
                                <SMColumn class="justify-content-center">
                                    <SMButton to="/" label="Home" />
                                </SMColumn>
                            </SMRow>
                        </template>
                    </SMDialog>
                </div>
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

            await axios.post("contact", {
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
