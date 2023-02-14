<template>
    <SMContainer>
        <SMRow>
            <SMDialog :narrow="formDone">
                <template v-if="!formDone">
                    <h1>Register</h1>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMRow>
                            <SMColumn>
                                <SMInput control="username" />
                            </SMColumn>
                            <SMColumn>
                                <SMInput
                                    control="password"
                                    type="password"></SMInput>
                            </SMColumn>
                        </SMRow>
                        <SMRow>
                            <SMColumn>
                                <SMInput control="first_name" />
                            </SMColumn>
                            <SMColumn>
                                <SMInput control="last_name" />
                            </SMColumn>
                        </SMRow>
                        <SMRow>
                            <SMColumn>
                                <SMInput control="email" />
                            </SMColumn>
                            <SMColumn>
                                <SMInput control="phone">
                                    This field is optional.
                                </SMInput>
                            </SMColumn>
                        </SMRow>
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
                                    icon="arrow-forward-outline" />
                            </template>
                        </SMFormFooter>
                    </SMForm>
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
import SMForm from "../components/SMForm.vue";
import { api } from "../helpers/api";
import { FormControl, FormObject } from "../helpers/form";
import {
    And,
    Custom,
    Email,
    Min,
    Password,
    Phone,
    Required,
} from "../helpers/validate";

import { debounce } from "../helpers/common";
import { useReCaptcha } from "vue-recaptcha-v3";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();

const checkUsername = (value: string): boolean | string => {
    if (lastUsernameCheck.value != form.username.value) {
        lastUsernameCheck.value = form.username.value;
        api.get({
            url: "/users",
            params: {
                username: form.username.value,
            },
        })
            .then((response) => {
                return "The username has already been taken.";
            })
            .catch((error) => {
                if (error.status != 404) {
                    return (
                        error.json?.message ||
                        "An unexpected server error occurred."
                    );
                }
            });
    }

    return true;
};

const formDone = ref(false);
const form = reactive(
    FormObject({
        first_name: FormControl("", Required()),
        last_name: FormControl("", Required()),
        email: FormControl("", And([Required(), Email()])),
        phone: FormControl("", Phone()),
        username: FormControl("", And([Min(4), Custom(checkUsername)])),
        password: FormControl("", And([Required(), Password()])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/register",
            body: {
                first_name: form.first_name.value,
                last_name: form.last_name.value,
                email: form.email.value,
                phone: form.phone.value,
                username: form.username.value,
                password: form.password.value,
                captcha_token: captcha,
            },
        });

        formDone.value = true;
    } catch (err) {
        form.apiErrors(err);
    }

    form.loading(false);
};

const lastUsernameCheck = ref("");

const debouncedFilter = debounce(checkUsername, 1000);
let oldUsernameValue = "";
watch(
    form,
    (value) => {
        if (value.username.value !== oldUsernameValue) {
            oldUsernameValue = value.username.value;
            debouncedFilter();
        }
    },
    { deep: true }
);
</script>
