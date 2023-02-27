<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <template #container>
            <SMDialog full class="mt-5" :narrow="formDone">
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
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useReCaptcha } from "vue-recaptcha-v3";
import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMForm from "../components/SMForm.vue";
import SMFormFooter from "../components/SMFormFooter.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import {
    And,
    Custom,
    Email,
    Min,
    Password,
    Phone,
    Required,
} from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
let abortController: AbortController | null = null;

const checkUsername = (value: string): boolean | string => {
    if (lastUsernameCheck.value != value) {
        lastUsernameCheck.value = value;

        if (abortController != null) {
            abortController.abort();
            abortController = null;
        }

        abortController = new AbortController();

        api.get({
            url: "/users",
            params: {
                username: value,
            },
            signal: abortController.signal,
        })
            .then((response) => {
                console.log("The username has already been taken.", response);
                return "The username has already been taken.";
            })
            .catch((error) => {
                console.log(error);
                if (error.status != 404) {
                    return (
                        error.json?.message ||
                        "An unexpected server error occurred."
                    );
                }

                return true;
            });
    }

    return true;
};

const formDone = ref(false);
const lastUsernameCheck = ref("");
const form = reactive(
    Form({
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
                first_name: form.controls.first_name.value,
                last_name: form.controls.last_name.value,
                email: form.controls.email.value,
                phone: form.controls.phone.value,
                username: form.controls.username.value,
                password: form.controls.password.value,
                captcha_token: captcha,
            },
        });

        formDone.value = true;
    } catch (error) {
        form.apiErrors(error);
    } finally {
        form.loading(false);
    }
};
</script>
