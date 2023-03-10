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
                    <SMFormFooter>
                        <template #right>
                            <SMButton :to="{ name: 'login' }" label="Login" />
                        </template>
                    </SMFormFooter>
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
let form = reactive(
    Form({
        code: FormControl("", And([Required(), Min(6), Max(6)])),
        password: FormControl("", And([Required(), Password()])),
    })
);

if (useRoute().query.code !== undefined) {
    let queryCode = useRoute().query.code;
    if (Array.isArray(queryCode)) {
        queryCode = queryCode[0];
    }

    form.controls.code.value = queryCode;
}

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/users/resetPassword",
            body: {
                code: form.controls.code.value,
                password: form.controls.password.value,
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
</script>
