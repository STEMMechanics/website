<template>
    <SMContainer :center="true">
        <template v-if="!formDone">
            <SMForm v-model="form" @submit="handleSubmit">
                <SMFormCard class="mt-5" narrow>
                    <template #header>
                        <h1>Email Verify</h1>
                    </template>
                    <template #body>
                        <p>
                            Enter your verification code below. If you have not
                            yet received one,
                            <router-link to="/resend-verify-email"
                                >request a new code</router-link
                            >.
                        </p>
                        <SMInput control="code" />
                    </template>
                    <template #footer>
                        <SMButtonRow>
                            <template #right>
                                <SMButton type="submit" label="Verify Code" />
                            </template>
                        </SMButtonRow>
                    </template>
                </SMFormCard>
            </SMForm>
        </template>
        <template v-else>
            <SMFormCard class="mt-5" narrow>
                <template #header>
                    <h1>Email Verified!</h1>
                </template>
                <template #body>
                    <p class="text-center">
                        Hurrah, Your email has been verified!
                    </p>
                </template>
                <template #footer>
                    <SMButtonRow>
                        <SMButton
                            type="primary"
                            block
                            :to="{ name: 'login' }"
                            label="Login" />
                    </SMButtonRow>
                </template>
            </SMFormCard>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute } from "vue-router";
import SMButton from "../components/SMButton.vue";
import SMFormCard from "../components/SMFormCard.vue";
import SMForm from "../components/SMForm.vue";
import SMButtonRow from "../components/SMButtonRow.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Max, Min, Required } from "../helpers/validate";
import { useToastStore } from "../store/ToastStore";

// const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
let form = reactive(
    Form({
        code: FormControl("", And([Required(), Min(6), Max(6)])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        // await recaptchaLoaded();
        // const captcha = await executeRecaptcha("submit");

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

    handleSubmit();
}
</script>
