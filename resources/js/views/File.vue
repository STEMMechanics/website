<template>
    <SMPageStatus
        v-if="pageLoading == false && pageStatus != 200"
        :status="pageStatus" />
    <SMLoading v-else-if="pageLoading == true"></SMLoading>
    <SMForm
        v-else-if="showForm == 'password'"
        :model-value="form"
        @submit="handleSubmit">
        <div
            class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
            <h3 class="mb-4">Password Required</h3>
            <p class="mb-2 text-sm">
                The file <strong>{{ fileName }}</strong> requires a password
                before you can view it:
            </p>
            <SMInput
                class="mb-4"
                control="password"
                type="password"
                label="File Password"
                autofocus />
            <div class="flex flex-justify-end">
                <input
                    type="submit"
                    class="font-medium block w-full md:inline-block md:w-auto px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    value="Submit" />
            </div>
        </div>
    </SMForm>
    <SMForm
        v-else-if="showForm == 'complete'"
        :model-value="form"
        @submit="handleSubmit">
        <div
            class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
            <h3 class="mb-4">Download Complete</h3>
            <p class="mb-2">
                If you have permission to view this document, your download
                should now begin.
            </p>
            <div class="flex flex-justify-between">
                <button
                    role="button"
                    class="font-medium block w-full md:inline-block md:w-auto px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    @click="handleLoad()">
                    Retry
                </button>
                <button
                    role="button"
                    class="font-medium block w-full md:inline-block md:w-auto px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    @click="handleClose()">
                    Close
                </button>
            </div>
        </div>
    </SMForm>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { api } from "../helpers/api";
import { useRoute } from "vue-router";
import { Media, MediaResponse } from "../helpers/api.types";
import SMForm from "../components/SMForm.vue";
import SMInput from "../components/SMInput.vue";
import SMLoading from "../components/SMLoading.vue";
import SMPageStatus from "../components/SMPageStatus.vue";
import { strCaseCmp } from "../helpers/string";
import { useUserStore } from "../store/UserStore";
import { Form, FormControl, FormObject } from "../helpers/form";
import { Required } from "../helpers/validate";

const pageStatus = ref(200);
const pageLoading = ref(true);
const showForm = ref("complete");
const fileUrl = ref("");
const fileName = ref("");
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
    window.setTimeout(() => {
        showForm.value = "complete";
    }, 1500);
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

const handleClose = () => {
    window.close();
};

/**
 * Handle page loading
 */
const handleLoad = async () => {
    const route = useRoute();
    if (
        route === undefined ||
        route.params === undefined ||
        route.params.id === undefined
    ) {
        pageStatus.value = 404;
    } else {
        const params = {
            id: route.params.id,
        };

        try {
            let result = await api.get({
                url: "/media/{id}",
                params: params,
            });

            if (result.status === 200) {
                const data = result.data as MediaResponse;
                const medium = data.medium as Media;
                fileName.value = medium.name;
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
                } else if (
                    strCaseCmp("password", medium.security_type) === true
                ) {
                    showForm.value = "password";
                } else {
                    /* unknown security type */
                    pageStatus.value = 403;
                }

                pageLoading.value = false;
            } else {
                pageStatus.value = result.status;
                pageLoading.value = false;
            }
        } catch (error) {
            pageStatus.value = error.status;
            pageLoading.value = false;
        }
    }
};

handleLoad();
</script>
