<template>
    <SMPage class="page-contact">
        <SMRow break-large>
            <SMColumn>
                <h1 class="text-left">Contact Us</h1>
                <h2>Questions & Support</h2>
                <p>
                    If you have a question or would like help with a project,
                    you can send it our way using the form on this page or be
                    emailing
                    <a href="mailto:hello@stemmechanics.com.au"
                        >hello@stemmechanics.com.au</a
                    >.
                </p>
                <h2>Wanting a workshop?</h2>
                <p>
                    We provide both public and private workshops as well as run
                    events on behalf of your organisation. If you would like to
                    discuss a potential opportunity, send us an email at
                    <a href="mailto:hello@stemmechanics.com.au"
                        >hello@stemmechanics.com.au</a
                    >.
                </p>
                <h2>Address</h2>
                <p>
                    We do not have a physical address as our workshops are
                    delivered across Queensland. Visit the
                    <router-link :to="{ name: 'workshop-list' }"
                        >workshops</router-link
                    >
                    page for each specific location.
                </p>
                <p>Official mail can be sent to the following address:</p>
                <div class="text-center">
                    <p class="font-size-90">
                        STEMMechanics<br />PO Box 36<br />Edmonton, QLD, 4869<br />Australia
                    </p>
                    <p class="font-size-90">
                        <strong>ABN: </strong>15 772 281 735
                    </p>
                </div>
            </SMColumn>
            <SMColumn>
                <div>
                    <SMDialog narrow>
                        <template v-if="!formSubmitted">
                            <SMForm v-model="form" @submit="handleSubmit">
                                <SMInput control="name" />
                                <SMInput control="email" type="email" />
                                <SMInput
                                    control="content"
                                    label="Message"
                                    type="textarea" />
                                <SMButton
                                    type="submit"
                                    block
                                    label="Send Message" />
                            </SMForm>
                        </template>
                        <template v-else>
                            <h1>Message Sent!</h1>
                            <p class="text-center">
                                Your message as been sent.<br />We will respond
                                as soon as we can.
                            </p>
                            <SMButton
                                block
                                :to="{ name: 'home' }"
                                label="Home" />
                        </template>
                    </SMDialog>
                </div>
            </SMColumn>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useReCaptcha } from "vue-recaptcha-v3";
import SMButton from "../components/SMButton.vue";
import SMDialog from "../components/SMDialog.vue";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Min, Required } from "../helpers/validate";

const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const form = reactive(
    Form({
        name: FormControl("", And([Required()])),
        email: FormControl("", And([Required(), Email()])),
        content: FormControl("", And([Required(), Min(8)])),
    })
);
const formSubmitted = ref(false);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/contact",
            body: {
                name: form.controls.name.value,
                email: form.controls.email.value,
                captcha_token: captcha,
                content: form.controls.content.value,
            },
        });

        formSubmitted.value = true;
    } catch (error) {
        form.error("A captcha error occurred. Try reloading the page.");
    } finally {
        form.loading(false);
    }
};
</script>

<style lang="scss">
.page-contact {
    background-color: #f8f8f8;
}
</style>
