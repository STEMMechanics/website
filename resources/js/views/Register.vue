<template>
    <SMContainer :center="true">
        <SMForm v-if="!userRegistered" v-model="form" @submit="handleSubmit">
            <SMFormCard full>
                <template #header>
                    <h2>Register</h2>
                    <p>
                        Create an account to access STEMMechanics courses and
                        features.
                    </p>
                </template>
                <template #body>
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
                </template>
                <template #footer-space-between>
                    <div class="small">
                        <span class="pr-1">Already have an account?</span
                        ><router-link to="/login">Log in</router-link>
                    </div>
                    <SMButton type="submit" label="Register" />
                </template>
            </SMFormCard>
        </SMForm>
        <SMFormCard v-else>
            <template #header>
                <h2>Email Sent!</h2>
            </template>
            <template #body>
                <p>
                    An email has been sent to you to confirm your details and to
                    finish registering your account.
                </p>
            </template>
            <template #footer>
                <SMButton type="primary" :to="{ name: 'home' }" label="Home" />
            </template>
        </SMFormCard>
    </SMContainer>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useReCaptcha } from "vue-recaptcha-v3";
import SMButton from "../components/SMButton.vue";
import SMFormCard from "../components/SMFormCard.vue";
import SMForm from "../components/SMForm.vue";
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

const checkUsername = async (value: string): Promise<boolean | string> => {
    try {
        if (lastUsernameCheck.value != value) {
            lastUsernameCheck.value = value;

            if (abortController != null) {
                abortController.abort();
                abortController = null;
            }

            abortController = new AbortController();

            await api.get({
                url: "/users",
                params: {
                    username: `=${value}`,
                },
                signal: abortController.signal,
            });

            return "The username has already been taken.";
        }

        return true;
    } catch (error) {
        return true;
    }
};

const userRegistered = ref(false);
const lastUsernameCheck = ref("");
let form = reactive(
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
                display_name: form.controls.username.value,
                captcha_token: captcha,
            },
        });

        userRegistered.value = true;
    } catch (error) {
        form.apiErrors(error);
    } finally {
        form.loading(false);
    }
};
</script>
