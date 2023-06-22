<template>
    <div
        class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
        <template v-if="formDone">
            <h1 class="mb-4">Forgot Password</h1>
            <p class="mb-4">
                Enter your email below to receive a password reset link.
            </p>
            <SMForm v-model="form" @submit="handleSubmit">
                <SMInput control="email" type="email" autofocus />
                <div
                    class="flex flex-justify-between items-center pt-4 flex-col sm:flex-row">
                    <div class="text-xs mb-4 sm:mb-0">
                        <span class="pr-1">Remember?</span
                        ><router-link :to="{ name: 'login' }"
                            >Log in</router-link
                        >
                    </div>
                    <input
                        v-if="!form.loading()"
                        type="submit"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        value="Send Email" />
                    <SMLoading v-else small />
                </div>
            </SMForm>
        </template>
        <template v-else>
            <h1 class="mb-4">Email Sent!</h1>
            <p class="mb-4">
                If that email address has been registered, you will receive an
                email with a reset password link in the next few minutes.
            </p>
            <div class="flex flex-justify-center items-center pt-4">
                <router-link
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    :to="{ name: 'home' }"
                    >Home</router-link
                >
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";
import { useToastStore } from "../store/ToastStore";
import SMLoading from "../components/SMLoading.vue";

// const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
let form = reactive(
    Form({
        email: FormControl("", And([Required(), Email()])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        // await recaptchaLoaded();
        // const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/users/forgotPassword",
            body: {
                email: form.controls.email.value,
                // captcha_token: captcha,
            },
        });

        formDone.value = true;
    } catch (error) {
        if (error.status == 422) {
            formDone.value = true;
        } else {
            form.apiErrors(error, (message) => {
                useToastStore().addToast({
                    title: "An error occurred",
                    content: message,
                    type: "danger",
                });
            });
        }
    } finally {
        form.loading(false);
    }
};
</script>
