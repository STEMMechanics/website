<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <SMRow>
            <SMDialog narrow>
                <template v-if="!formDone">
                    <h1>Unsubscribe</h1>
                    <p>
                        If you would like to unsubscribe from our mailing list,
                        you have come to the right page!
                    </p>
                    <SMForm v-model="form" @submit="handleSubmit">
                        <SMInput control="email" />
                        <SMFormFooter>
                            <template #right>
                                <SMButton type="submit" label="Unsubscribe" />
                            </template>
                        </SMFormFooter>
                    </SMForm>
                </template>
                <template v-else>
                    <h1>Unsubscribed</h1>
                    <p class="text-center">
                        You have now been unsubscribed from our newsletter.
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
import { useRoute } from "vue-router";
import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMForm from "../components/SMForm.vue";
import SMFormFooter from "../components/SMFormFooter.vue";
import SMInput from "../components/SMInput.vue";

import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const formDone = ref(false);
const form = reactive(
    Form({
        email: FormControl("", And([Required(), Email()])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.delete({
            url: "/subscriptions",
            body: {
                email: form.controls.email.value,
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

if (useRoute().query.email !== undefined) {
    let queryEmail = useRoute().query.email;
    if (Array.isArray(queryEmail)) {
        queryEmail = queryEmail[0];
    }

    form.controls.email.value = queryEmail;
    handleSubmit();
}
</script>
