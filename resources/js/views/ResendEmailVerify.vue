<template>
    <SMPage>
        <SMRow>
            <SMFormCard class="mt-5" narrow>
                <template v-if="!formDone">
                    <h1>Resend Verify Email</h1>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="email" />
                        <SMButtonRow>
                            <template #left>
                                <div class="small">
                                    <span class="pr-1">Stuck?</span
                                    ><router-link to="/contact"
                                        >Contact Us</router-link
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
                        If that email address has been registered, and you still
                        need to verify your email, you will receive an email
                        with a new verify code.
                    </p>
                    <SMButtonRow>
                        <template #right>
                            <SMButton :to="{ name: 'home' }" label="Home" />
                        </template>
                    </SMButtonRow>
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
            url: "/users/resendVerifyEmailCode",
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
            form.apiErrors(error);
        }
    } finally {
        form.loading(false);
    }
};
</script>
