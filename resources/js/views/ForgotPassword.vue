<template>
    <SMPage>
        <SMRow>
            <SMFormCard class="mt-5">
                <template v-if="!formDone">
                    <h1>Forgot Password</h1>
                    <p>
                        Enter your email below to receive a password reset link.
                    </p>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="email" type="email" autofocus />
                        <SMButtonRow>
                            <template #left>
                                <div class="small">
                                    <span class="pr-1">Remember?</span
                                    ><router-link :to="{ name: 'login' }"
                                        >Log in</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Send"
                                    icon="arrow-forward-outline" />
                            </template>
                        </SMButtonRow>
                    </SMForm>
                </template>
                <template v-else>
                    <h1>Email Sent!</h1>
                    <p class="text-center">
                        If that email address has been registered, you will
                        receive an email with a reset password link in the next
                        few minutes.
                    </p>
                    <SMRow class="pb-2">
                        <SMColumn class="justify-content-center">
                            <SMButton :to="{ name: 'home' }" label="Home" />
                        </SMColumn>
                    </SMRow>
                </template>
            </SMFormCard>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
// import { useReCaptcha } from "vue-recaptcha-v3";
import SMButton from "../components/SMButton.vue";
import SMFormCard from "../components/SMFormCard.vue";
import SMForm from "../components/SMForm.vue";
import SMButtonRow from "../components/SMButtonRow.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";
import { useToastStore } from "../store/ToastStore";

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
