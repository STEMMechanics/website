<template>
    <SMContainer :center="true">
        <SMForm v-if="!userRegistered" v-model="form" @submit="handleSubmit">
            <SMFormCard>
                <template #header>
                    <h2>Register</h2>
                    <p>
                        Create an account to access STEMMechanics courses and
                        features.
                    </p>
                </template>
                <template #body>
                    <SMFormError v-model="form" />
                    <SMInput control="email" autofocus />
                    <SMInput control="password" type="password" />
                    <SMInput control="display_name" label="Display Name" />
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
// import { useReCaptcha } from "vue-recaptcha-v3";
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
    Required,
} from "../helpers/validate";
import SMFormError from "../components/SMFormError.vue";

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
        email: FormControl("", And([Required(), Email()])),
        password: FormControl("", And([Required(), Password()])),
        display_name: FormControl("", And([Min(4)])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await api.post({
            url: "/register",
            body: {
                email: form.controls.email.value,
                password: form.controls.password.value,
                display_name: form.controls.display_name.value,
            },
        });

        userRegistered.value = true;
    } catch (error) {
        console.log(error);
        form.apiErrors(error);
    } finally {
        form.loading(false);
    }
};
</script>
