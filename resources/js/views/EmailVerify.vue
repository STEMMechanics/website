<template>
    <div
        class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
        <template v-if="!formDone">
            <SMForm ref="formObject" v-model="form" @submit="handleSubmit">
                <h1 class="mb-4">Email Verify</h1>
                <p class="mb-4">
                    Enter your verification code below. If you have not yet
                    received one,
                    <router-link to="/resend-verify-email"
                        >request a new code</router-link
                    >.
                </p>
                <SMInput class="mb-4" autofocus control="code" />
                <div class="flex flex-justify-end items-center pt-4">
                    <input
                        v-if="!form.loading()"
                        type="submit"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        value="Verify Code" />
                    <SMLoading v-else small />
                </div>
            </SMForm>
        </template>
        <template v-else>
            <h1 class="mb-4">Email Verified!</h1>
            <p class="mb-4">Hurrah, Your email has been verified!</p>
            <div class="flex flex-justify-center items-center pt-4">
                <router-link
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    :to="{ name: 'login' }"
                    >Login</router-link
                >
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute } from "vue-router";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Max, Min, Required } from "../helpers/validate";
import { useToastStore } from "../store/ToastStore";
import SMLoading from "../components/SMLoading.vue";

// const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
const formObject = ref(null);
let form = reactive(
    Form({
        code: FormControl("", And([Required(), Min(6), Max(6)])),
    }),
);

const handleSubmit = async () => {
    try {
        form.loading(true);

        await api.post({
            url: "/users/verifyEmail",
            body: {
                code: form.controls.code.value,
                // captcha_token: captcha,
            },
        });

        formDone.value = true;
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

if (useRoute().query.code !== undefined) {
    const code = useRoute().query.code;

    if (Array.isArray(code)) {
        if (code.length > 0) {
            form.controls.code.value = code[0];
        }
    } else {
        form.controls.code.value = code;
    }

    // handleSubmit();
    formObject.value.handleSubmit();
}
</script>
