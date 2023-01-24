<template>
    <SMContainer>
        <SMRow>
            <SMDialog :narrow="formDone" :loading="formLoading">
                <template v-if="!formDone">
                    <h1>Register</h1>
                    <SMMessage
                        v-if="formMessage.message"
                        :type="formMessage.type"
                        :message="formMessage.message"
                        :icon="formMessage.icon" />
                    <form @submit.prevent="submit">
                        <SMRow>
                            <SMColumn>
                                <SMInput
                                    v-model="formData.username.value"
                                    label="Username"
                                    required
                                    :error="formData.username.error"
                                    @blur="
                                        fieldValidate(formData.username)
                                    "></SMInput>
                            </SMColumn>
                            <SMColumn>
                                <SMInput
                                    v-model="formData.password.value"
                                    type="password"
                                    label="Password"
                                    required
                                    :error="formData.password.error"
                                    @blur="
                                        fieldValidate(formData.password)
                                    "></SMInput>
                            </SMColumn>
                        </SMRow>
                        <SMRow>
                            <SMColumn>
                                <SMInput
                                    v-model="formData.first_name.value"
                                    label="First Name"
                                    required
                                    :error="formData.first_name.error"
                                    @blur="
                                        fieldValidate(formData.first_name)
                                    " />
                            </SMColumn>
                            <SMColumn>
                                <SMInput
                                    v-model="formData.last_name.value"
                                    label="Last Name"
                                    required
                                    :error="formData.last_name.error"
                                    @blur="fieldValidate(formData.last_name)" />
                            </SMColumn>
                        </SMRow>
                        <SMRow>
                            <SMColumn>
                                <SMInput
                                    v-model="formData.email.value"
                                    label="Email"
                                    required
                                    :error="formData.email.error"
                                    @blur="fieldValidate(formData.email)" />
                            </SMColumn>
                            <SMColumn>
                                <SMInput
                                    v-model="formData.phone.value"
                                    label="Phone Number"
                                    :error="formData.phone.error"
                                    @blur="fieldValidate(formData.phone)" />
                            </SMColumn>
                        </SMRow>
                        <SMCaptchaNotice />
                        <SMFormFooter>
                            <template #left>
                                <div>
                                    <span class="pr-1"
                                        >Already have an account?</span
                                    ><router-link to="/login"
                                        >Log in</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Register"
                                    icon="fa-solid fa-arrow-right" />
                            </template>
                        </SMFormFooter>
                    </form>
                </template>
                <template v-else>
                    <h1>Email Sent!</h1>
                    <p class="text-center">
                        An email has been sent to you to confirm your details
                        and to finish registering your account.
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
import { ref, reactive, watch } from "vue";
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
import { debounce } from "../helpers/common";
import SMCaptchaNotice from "../components/SMCaptchaNotice.vue";
import { useReCaptcha } from "vue-recaptcha-v3";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const lastUsernameCheck = ref("");
const formLoading = ref(false);
const formDone = ref(false);
const formMessage = reactive({
    message: "",
    type: "error",
    icon: "",
});
const formData = reactive({
    first_name: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A first name is needed",
            min: 2,
            min_message: "Your first name should be at least 2 letters long",
        },
    },
    last_name: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A last name is needed",
            min: 2,
            min_message: "Your last name should be at least 2 letters long",
        },
    },
    email: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A email address is needed",
            email: true,
            email_message: "Your email address is not correct",
        },
    },
    phone: {
        value: "",
        error: "",
        rules: {
            phone: true,
            phone_message: "Your phone number does not look correct",
        },
    },
    username: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A username is needed",
            min: 4,
            min_message: "Your username needs to be at least %d characters",
            custom: () => {
                checkUsername();
            },
        },
    },
    password: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A password is needed",
            min: 8,
            min_message: "Your password needs to be at least %d characters",
            password: "special",
            password_message:
                "Your password needs to have at least a letter, a number and a special character",
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

            let res = await axios.post("register", {
                first_name: formData.first_name.value,
                last_name: formData.last_name.value,
                email: formData.email.value,
                phone: formData.phone.value,
                username: formData.username.value,
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

const checkUsername = async () => {
    try {
        if (
            formData.username.value.length >= 4 &&
            lastUsernameCheck.value != formData.username.value
        ) {
            lastUsernameCheck.value = formData.username.value;
            await axios.get(`users?username=${formData.username.value}`);
            formData.username.error = "The username has already been taken.";
        }
    } catch (error) {
        if (error.response.status == 404) {
            formData.username.error = "";
        } else {
            formMessage.type = "error";
            formMessage.icon = "fa-solid fa-circle-exclamation";
            formMessage.message =
                error.response.message ||
                "An unexpected server error occurred.";
        }
    }
};

const debouncedFilter = debounce(checkUsername, 1000);
let oldUsernameValue = "";
watch(
    formData,
    (value) => {
        if (value.username.value !== oldUsernameValue) {
            oldUsernameValue = value.username.value;
            debouncedFilter();
        }
    },
    { deep: true }
);
</script>
