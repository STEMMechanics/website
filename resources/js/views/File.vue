<template>
    <SMPageStatus
        v-if="pageLoading == false && pageStatus != 200"
        :status="pageStatus" />
    <SMLoading v-else-if="pageLoading == true"></SMLoading>
    <SMForm
        v-else-if="showPasswordForm == true"
        :model-value="form"
        @submit="handleSubmit">
        <SMFormCard>
            <template #header>
                <h3>Password Required</h3>
                <p>This file requires a password before it can be viewed</p>
            </template>
            <template #body>
                <SMInput
                    control="password"
                    type="password"
                    label="File Password"
                    autofocus />
            </template>
            <template #footer-space-between>
                <input role="button" type="submit" value="OK" />
            </template>
        </SMFormCard>
    </SMForm>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { api } from "../helpers/api";
import { useRoute } from "vue-router";
import { Media } from "../helpers/api.types";
import SMLoading from "../components/SMLoading.vue";
import { strCaseCmp } from "../helpers/string";
import { useUserStore } from "../store/UserStore";
import { Form, FormControl, FormObject } from "../helpers/form";
import { Required } from "../helpers/validate";

const pageStatus = ref(200);
const pageLoading = ref(true);
const showPasswordForm = ref(false);
const fileUrl = ref("");
const userStore = useUserStore();

const form: FormObject = reactive(
    Form({
        password: FormControl("", Required()),
    }),
);

/*
 * Download file from URL
 */
const downloadFile = (params = {}) => {
    let url = fileUrl.value;

    // Check if the URL already contains query parameters
    const hasQueryParameters = url.includes("?");

    if (Object.keys(params).length > 0) {
        url += hasQueryParameters ? "&" : "?";
        url += Object.keys(params)
            .map(
                (key) =>
                    encodeURIComponent(key) +
                    "=" +
                    encodeURIComponent(params[key]),
            )
            .join("&");
    }

    window.location.href = url;
};

/*
 * Handle password form submit
 */
const handleSubmit = () => {
    const params = {
        password: form.controls.password.value,
    };

    downloadFile(params);
};

/**
 * Handle page loading
 */
const handleLoad = async () => {
    const route = useRoute();
    if (route.params.id === undefined) {
        pageStatus.value = 403;
    } else {
        const params = {
            id: route.params.id,
        };

        let result = await api.get({
            url: "/media/:id",
            params: params,
        });

        if (result.status === 200) {
            const medium = result.data as Media;
            fileUrl.value = medium.url;

            if (medium.security_type === "") {
                downloadFile();
            } else if (
                strCaseCmp("permission", medium.security_type) === true &&
                userStore.id
            ) {
                const params = {
                    token: userStore.token,
                };

                downloadFile(params);
            } else if (strCaseCmp("password", medium.security_type) === true) {
                showPasswordForm.value = true;
            } else {
                /* unknown security type */
                pageStatus.value = 403;
            }
        } else {
            pageStatus.value = result.status;
        }
    }
};

handleLoad();
</script>
