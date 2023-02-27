<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <SMRow>
            <SMDialog class="mt-5">
                <template v-if="!formDone">
                    <h1>Forgot Password</h1>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="username" />
                        <SMFormFooter>
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
                        </SMFormFooter>
                    </SMForm>
                </template>
                <template v-else>
                    <h1>Email Sent!</h1>
                    <p class="text-center">
                        If that username has been registered, you will receive
                        an email with a reset password link in the next few
                        minutes.
                    </p>
                    <SMRow class="pb-2">
                        <SMColumn class="justify-content-center">
                            <SMButton :to="{ name: 'home' }" label="Home" />
                        </SMColumn>
                    </SMRow>
                </template>
            </SMDialog>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useReCaptcha } from "vue-recaptcha-v3";
import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMFormFooter from "../components/SMFormFooter.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Min, Required } from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
const form = reactive(
    Form({
        username: FormControl("", And([Required(), Min(4)])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/users/forgotPassword",
            body: {
                username: form.controls.username.value,
                captcha_token: captcha,
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
