<template>
    <SMPage no-breadcrumbs background="/img/background.jpg">
        <SMDialog class="mt-5">
            <h1>Log in</h1>
            <SMForm v-model="form" @submit="handleSubmit">
                <SMInput control="username">
                    <router-link to="/forgot-username"
                        >Forgot username?</router-link
                    >
                </SMInput>
                <SMInput control="password" type="password">
                    <router-link to="/forgot-password"
                        >Forgot password?</router-link
                    >
                </SMInput>
                <SMFormFooter>
                    <template #left>
                        <div class="small">
                            <span class="pr-1">Need an account?</span
                            ><router-link to="/register">Register</router-link>
                        </div>
                    </template>
                    <template #right>
                        <SMButton
                            type="submit"
                            label="Log in"
                            icon="arrow-forward-outline" />
                    </template>
                </SMFormFooter>
            </SMForm>
        </SMDialog>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive } from "vue";
import { useUserStore } from "../store/UserStore";
import { useRoute, useRouter } from "vue-router";
import { api } from "../helpers/api";
import { FormObject, FormControl } from "../helpers/form";
import { And, Min, Required, Password } from "../helpers/validate";
import SMPage from "../components/SMPage.vue";
import SMInput from "../components/SMInput.vue";
import SMButton from "../components/SMButton.vue";
import SMFormFooter from "../components/SMFormFooter.vue";
import SMDialog from "../components/SMDialog.vue";
import SMForm from "../components/SMForm.vue";

const router = useRouter();
const userStore = useUserStore();
const form = reactive(
    FormObject({
        username: FormControl("", And([Required(), Min(4)])),
        password: FormControl("", Password()),
    })
);

const redirect = useRoute().query.redirect;

const handleSubmit = async () => {
    form.message();
    form.loading(true);

    try {
        let res = await api.post({
            url: "/login",
            body: {
                username: form.username.value,
                password: form.password.value,
            },
        });

        userStore.setUserDetails(res.json.user);
        userStore.setUserToken(res.json.token);
        if (redirect !== undefined) {
            if (redirect.startsWith("api/")) {
                window.location.href =
                    redirect + "?token=" + encodeURIComponent(res.json.token);
            } else {
                router.push({ path: redirect });
            }
        } else {
            router.push({ name: "dashboard" });
        }
    } catch (err) {
        console.log(err);
        form.apiErrors(err);
    }

    form.loading(false);
};

if (userStore.token) {
    userStore.clearUser();
}
</script>
