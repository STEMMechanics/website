<template>
    <div
        class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
        <SMForm v-model="form" @submit="handleSubmit">
            <h1 class="mb-4">Log in</h1>
            <p class="mb-4">
                Enter your website login details to view your account.
            </p>
            <SMInput class="mb-4" control="email" autofocus type="email">
            </SMInput>
            <SMInput class="mb-4" control="password" type="password">
                <router-link to="/forgot-password"
                    >Forgot password?</router-link
                >
            </SMInput>
            <div
                class="flex flex-justify-between items-center pt-4 flex-col sm:flex-row">
                <div class="text-xs mb-4 sm:mb-0">
                    <span class="pr-1">Need an account?</span
                    ><router-link to="/register">Register</router-link>
                </div>
                <input
                    v-if="!form.loading()"
                    type="submit"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    value="Log in" />
                <SMLoading v-else small />
            </div>
        </SMForm>
    </div>
</template>

<script setup lang="ts">
import { reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { LoginResponse } from "../helpers/api.types";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";
import { useUserStore } from "../store/UserStore";
import { useToastStore } from "../store/ToastStore";
import SMLoading from "../components/SMLoading.vue";

const userStore = useUserStore();

const router = useRouter();
let form = reactive(
    Form({
        email: FormControl("", And([Required(), Email()])),
        password: FormControl("", Required()),
    })
);

const redirectQuery = useRoute().query.redirect;

/**
 * Handle the user submitting the login form.
 */
const handleSubmit = async () => {
    form.message();
    form.loading(true);

    try {
        let result = await api.post({
            url: "/login",
            body: {
                email: form.controls.email.value,
                password: form.controls.password.value,
            },
        });

        const login = result.data as LoginResponse;

        userStore.setUserDetails(login.user);
        userStore.setUserToken(login.token);
        if (redirectQuery !== undefined) {
            const redirect = Array.isArray(redirectQuery)
                ? redirectQuery[0]
                : redirectQuery;

            router.push(decodeURIComponent(redirect));
        } else {
            router.push({ name: "dashboard" });
        }
    } catch (error) {
        form.controls.password.value = "";
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

if (userStore.token) {
    userStore.clearUser();
}
</script>
