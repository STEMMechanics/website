<template>
    <div
        class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
        <template v-if="!formDone">
            <h1 class="mb-4">Reset Password</h1>
            <SMForm v-model="form" @submit="handleSubmit">
                <SMInput class="mb-4" control="code" />
                <SMInput class="mb-4" control="password" type="password" />
                <div
                    class="flex flex-justify-between items-center pt-4 flex-col sm:flex-row">
                    <div class="text-xs mb-4 sm:mb-0">
                        <router-link :to="{ name: 'forgot-password' }"
                            >Resend Code</router-link
                        >
                    </div>
                    <input
                        v-if="!form.loading()"
                        type="submit"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        value="Reset Password" />
                    <SMLoading v-else small />
                </div>
            </SMForm>
        </template>
        <template v-else>
            <h1 class="mb-4">Password Reset!</h1>
            <p class="mb-4">Hurrah, Your password has been changed!</p>
            <div class="flex flex-justify-center items-center pt-4">
                <router-link
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    :to="{ name: 'login' }"
                    >Log in</router-link
                >
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute } from "vue-router";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import { api } from "../helpers/api";
import { Form, FormControl } from "../helpers/form";
import { And, Max, Min, Password, Required } from "../helpers/validate";
import { useToastStore } from "../store/ToastStore";
import SMLoading from "../components/SMLoading.vue";

const formDone = ref(false);
let form = reactive(
    Form({
        code: FormControl("", And([Required(), Min(6), Max(6)])),
        password: FormControl("", And([Required(), Password()])),
    }),
);

if (useRoute().query.code !== undefined) {
    let queryCode = useRoute().query.code;
    if (Array.isArray(queryCode)) {
        queryCode = queryCode[0];
    }

    form.controls.code.value = queryCode;
}

const handleSubmit = async () => {
    try {
        form.loading(true);
        await api.post({
            url: "/users/resetPassword",
            body: {
                code: form.controls.code.value,
                password: form.controls.password.value,
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
</script>
