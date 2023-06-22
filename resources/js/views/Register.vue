<template>
    <div
        class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
        <SMForm v-if="!userRegistered" v-model="form" @submit="handleSubmit">
            <h1 class="mb-4">Register</h1>
            <p class="mb-4">
                Create an account to access STEMMechanics courses and features.
            </p>
            <SMFormError v-model="form" />
            <SMInput class="mb-4" control="email" autofocus type="email" />
            <SMInput class="mb-4" control="password" type="password" />
            <SMInput class="mb-4" control="display_name" label="Display Name" />
            <div
                class="flex flex-justify-between items-center pt-4 flex-col sm:flex-row">
                <div class="text-xs mb-4 sm:mb-0">
                    <span class="pr-1">Already have an account?</span
                    ><router-link to="/login">Log in</router-link>
                </div>
                <input
                    type="submit"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    value="Register" />
            </div>
        </SMForm>
        <div v-else>
            <h1 class="mb-4">Email Sent!</h1>
            <p class="mb-4">
                An email has been sent to you to confirm your details and to
                finish registering your account.
            </p>
            <div class="text-center">
                <router-link
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    :to="{ name: 'home' }"
                    >Home</router-link
                >
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
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
import { useToastStore } from "../store/ToastStore";

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
        form.apiErrors(error, (message) => {
            useToastStore().addToast({
                title: "An error occurred",
                content: message,
                type: "danger",
            });
        });
    } finally {
        form.loading(false);
    }
};
</script>
