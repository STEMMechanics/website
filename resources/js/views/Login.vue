<template>
    <SMContainer :center="true">
        <SMForm v-model="form" @submit="handleSubmit">
            <SMFormCard>
                <template #header>
                    <h2>Log in</h2>
                    <p>
                        Enter your website login details to view your account.
                    </p>
                </template>
                <template #body>
                    <SMInput control="email" autofocus type="email"> </SMInput>
                    <SMInput control="password" type="password">
                        <router-link to="/forgot-password"
                            >Forgot password?</router-link
                        >
                    </SMInput>
                </template>
                <template #footer>
                    <SMButtonRow>
                        <template #left>
                            <div class="small">
                                <span class="pr-1">Need an account?</span
                                ><router-link to="/register"
                                    >Register</router-link
                                >
                            </div>
                        </template>
                        <template #right>
                            <SMButton
                                :form="form"
                                type="submit"
                                label="Log in" />
                        </template>
                    </SMButtonRow>
                </template>
            </SMFormCard>
        </SMForm>
    </SMContainer>
</template>

<script setup lang="ts">
import { reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMButton from "../components/SMButton.vue";
import SMContainer from "../components/SMContainer.vue";
import SMForm from "../components/SMForm.vue";
import SMFormCard from "../components/SMFormCard.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { LoginResponse } from "../helpers/api.types";
import { Form, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";
import { useUserStore } from "../store/UserStore";
import SMButtonRow from "../components/SMButtonRow.vue";

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
    } catch (err) {
        form.controls.password.value = "";
        form.apiErrors(err);
    } finally {
        form.loading(false);
    }
};

if (userStore.token) {
    userStore.clearUser();
}
</script>
