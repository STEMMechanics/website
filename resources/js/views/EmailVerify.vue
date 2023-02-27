<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <SMRow>
            <SMDialog class="mt-5" narrow>
                <template v-if="!formDone">
                    <h1>Email Verify</h1>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="code" />
                        <SMFormFooter>
                            <template #left>
                                <div class="small">
                                    <router-link to="/resend-verify-email"
                                        >Resend Code</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Verify Code"
                                    icon="arrow-forward-outline" />
                            </template>
                        </SMFormFooter>
                    </SMForm>
                </template>
                <template v-else>
                    <h1>Email Verified!</h1>
                    <p class="text-center">
                        Hurrah, Your email has been verified!
                    </p>
                    <SMRow class="pb-2">
                        <SMColumn class="justify-content-center">
                            <SMButton :to="{ name: 'login' }" label="Login" />
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
import { useRoute } from "vue-router";
import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMForm from "../components/SMForm.vue";
import SMFormFooter from "../components/SMFormFooter.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Max, Min, Required } from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
const form = reactive(
    Form({
        code: FormControl("", And([Required(), Min(6), Max(6)])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/users/verifyEmail",
            body: {
                code: form.controls.code.value,
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

if (useRoute().query.code !== undefined) {
    const code = useRoute().query.code;

    if (Array.isArray(code)) {
        if (code.length > 0) {
            form.controls.code.value = code[0];
        }
    } else {
        form.controls.code.value = code;
    }

    handleSubmit();
}
</script>
