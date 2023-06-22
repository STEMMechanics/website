<template>
    <div
        class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
        <template v-if="!formDone">
            <SMForm v-model="form" @submit="handleSubmit">
                <h1 class="mb-4">Resend Email</h1>
                <p class="mb-4">
                    If you have not received your verification email yet, we can
                    send you another one.
                </p>
                <SMInput control="email" type="email" />
                <div
                    class="flex flex-justify-between items-center pt-4 flex-col sm:flex-rowpo">
                    <div class="text-xs mb-4 sm:mb-0">
                        <span>Stuck?</span
                        ><router-link to="/contact">Contact Us</router-link>
                    </div>
                    <input
                        v-if="!form.loading()"
                        type="submit"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        value="Send" />
                    <SMLoading v-else small />
                </div>
            </SMForm>
        </template>
        <template v-else>
            <h1 class="mb-4">Email Sent!</h1>
            <p class="mb-4">
                If that email address has been registered, and you still need to
                verify your email, you will receive an email with a new verify
                code.
            </p>
            <div class="flex flex-justify-center items-center pt-4">
                <router-link
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    :to="{ name: 'home' }"
                    >Home</router-link
                >
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";
import { useToastStore } from "../store/ToastStore";
import SMLoading from "../components/SMLoading.vue";

const formDone = ref(false);
let form = reactive(
    Form({
        email: FormControl("", And([Required(), Email()])),
    })
);

const handleSubmit = async () => {
    form.loading(true);

    try {
        await api.post({
            url: "/users/resendVerifyEmailCode",
            body: {
                email: form.controls.email.value,
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
