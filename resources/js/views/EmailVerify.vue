<template>
    <SMPage>
        <SMRow>
            <SMFormCard class="mt-5" narrow>
                <template v-if="!formDone">
                    <h1>Email Verify</h1>
                    <p>
                        Enter your verification code below. If you have not yet
                        received one,
                        <router-link to="/resend-verify-email"
                            >request a new code</router-link
                        >.
                    </p>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="code" />
                        <SMButtonRow>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Verify Code"
                                    icon="arrow-forward-outline" />
                            </template>
                        </SMButtonRow>
                    </SMForm>
                </template>
                <template v-else>
                    <h1>Email Verified!</h1>
                    <p class="text-center">
                        Hurrah, Your email has been verified!
                    </p>
                    <SMButtonRow>
                        <template #right>
                            <SMButton :to="{ name: 'login' }" label="Login" />
                        </template>
                    </SMButtonRow>
                </template>
            </SMFormCard>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useReCaptcha } from "vue-recaptcha-v3";
import { useRoute } from "vue-router";
import SMButton from "../components/SMButton.vue";
import SMFormCard from "../components/SMFormCard.vue";
import SMForm from "../components/SMForm.vue";
import SMButtonRow from "../components/SMButtonRow.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Max, Min, Required } from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
let form = reactive(
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
