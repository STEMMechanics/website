<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <SMRow>
            <SMDialog narrow>
                <template v-if="!formDone">
                    <h1>Reset Password</h1>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="code" />
                        <SMInput control="password" type="password" />
                        <SMFormFooter>
                            <template #left>
                                <div class="small">
                                    <router-link
                                        :to="{ name: 'forgot-password' }"
                                        >Resend Code</router-link
                                    >
                                </div>
                            </template>
                            <template #right>
                                <SMButton
                                    type="submit"
                                    label="Reset Password"
                                    icon="arrow-forward-outline" />
                            </template>
                        </SMFormFooter>
                    </SMForm>
                </template>
                <template v-else>
                    <h1>Password Reset!</h1>
                    <p class="text-center">
                        Hurrah, Your password has been changed!
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
import { And, Max, Min, Password, Required } from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
const form = reactive(
    Form({
        code: FormControl("", And([Required(), Min(6), Max(6)])),
        password: FormControl("", And([Required(), Password()])),
    })
);

if (useRoute().query.code !== undefined) {
    form.code.value = useRoute().query.code;
}

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/users/resetPassword",
            body: {
                code: form.code.value,
                password: form.password.value,
                captcha_token: captcha,
            },
        });

        formDone.value = true;
    } catch (error) {
        form.apiError(error);
    }

    form.loading(false);
};
</script>
