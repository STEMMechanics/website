<template>
    <SMContainer :page-error="pageError" permission="admin/events">
        <SMRow>
            <SMDialog :loading="formLoading">
                <h1>{{ page_title }}</h1>
                <SMMessage
                    v-if="formMessage.message"
                    :icon="formMessage.icon"
                    :type="formMessage.type"
                    :message="formMessage.message" />
                <form @submit.prevent="submit">
                    <SMRow>
                        <SMColumn
                            ><SMInput
                                v-model="formData.title.value"
                                label="Title"
                                required
                                :error="formData.title.error"
                                @blur="fieldValidate(formData.title)"
                        /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMSelect
                                v-model="formData.location.value"
                                label="Location"
                                :options="{
                                    online: 'Online',
                                    physical: 'Physical',
                                }"
                                @change="fieldValidate(formData.address)" />
                        </SMColumn>
                        <SMColumn
                            ><SMInput
                                v-if="formData.location.value !== 'online'"
                                v-model="formData.address.value"
                                :label="address_data?.title"
                                type="text"
                                :required="address_data?.required"
                                :error="formData.address.error"
                                @blur="fieldValidate(formData.address)"
                        /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMDatepicker
                                v-model="formData.start_at.value"
                                label="Start Date/Time"
                                :error="formData.start_at.error"
                                required
                                @blur="
                                    fieldValidate(formData.start_at)
                                "></SMDatepicker>
                        </SMColumn>
                        <SMColumn>
                            <SMDatepicker
                                v-model="formData.end_at.value"
                                label="End Date/Time"
                                :error="formData.end_at.error"
                                required
                                @blur="
                                    fieldValidate(formData.end_at)
                                "></SMDatepicker>
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMDatepicker
                                v-model="formData.publish_at.value"
                                label="Publish Date/Time"
                                :error="formData.publish_at.error"
                                @blur="
                                    fieldValidate(formData.publish_at)
                                "></SMDatepicker>
                        </SMColumn>
                        <SMColumn>
                            <SMSelect
                                v-model="formData.status.value"
                                label="Status"
                                :options="{
                                    draft: 'Draft',
                                    open: 'Open',
                                    closed: 'Closed',
                                    cancelled: 'Cancelled',
                                }" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMSelect
                                v-model="formData.registration_type.value"
                                label="Registration"
                                :options="{
                                    none: 'None',
                                    email: 'Email',
                                    link: 'Link',
                                }"
                                @change="
                                    fieldValidate(formData.registration_data)
                                " />
                        </SMColumn>
                        <SMColumn>
                            <SMInput
                                v-if="registration_data?.visible"
                                v-model="formData.registration_data.value"
                                :label="registration_data?.title"
                                :type="registration_data?.type"
                                required
                                :error="formData.registration_data.error"
                                @blur="
                                    fieldValidate(formData.registration_data)
                                " />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                v-model="formData.hero.value"
                                type="media"
                                label="Hero image"
                                :error="formData.hero.error"
                                required />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMEditor
                                v-model:srcContent="formData.content.value"
                                :mime-types="[
                                    'image/png',
                                    'image/jpeg',
                                    'image/gif',
                                ]"
                                @trix-attachment-add="attachmentAdd" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMFormFooter>
                            <template #right>
                                <SMButton type="submit" label="Save" />
                            </template>
                        </SMFormFooter>
                    </SMRow>
                </form>
            </SMDialog>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMSelect from "../../components/SMSelect.vue";
import SMDatepicker from "../../components/SMDatePicker.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../../helpers/validation";
import { useRoute } from "vue-router";
import { timestampLocalToUtc, timestampUtcToLocal } from "../../helpers/common";

const route = useRoute();
const formLoading = ref(false);
const page_title = route.params.id ? "Edit Event" : "Create New Event";
const pageError = ref(200);

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const address_data = computed(() => {
    let data = {
        title: "",
        required: false,
    };

    if (formData?.location.value === "online") {
        data.required = false;
    } else if (formData?.location.value === "physical") {
        data.title = "Address";
        data.required = true;
    }

    return data;
});

const registration_data = computed(() => {
    let data = {
        visible: false,
        title: "",
        type: "text",
    };

    if (formData?.registration_type.value === "email") {
        data.visible = true;
        data.title = "Registration email";
        data.type = "email";
    } else if (formData?.registration_type.value === "link") {
        data.visible = true;
        data.title = "Registration URL";
        data.type = "url";
    }

    return data;
});

const formData = reactive({
    title: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "An event title is required",
            min: 6,
            min_message: "Your event title should be at least 6 letters long",
        },
    },
    location: {
        value: "online",
        error: "",
    },
    address: {
        value: "",
        error: "",
        rules: {
            required: () => {
                return address_data?.value.required;
            },
            required_message: "An address is required",
        },
    },
    start_at: {
        value: null,
        error: "",
        rules: {
            required: true,
            datetime: true,
        },
    },
    end_at: {
        value: null,
        error: "",
        rules: {
            required: true,
            datetime: true,
            afterdate: () => {
                return formData.start_at.value;
            },
            afterdate_message:
                "The ending date/time must be after the starting date/time.",
        },
    },
    publish_at: {
        value: null,
        error: "",
    },
    status: {
        value: "",
        error: "",
    },
    registration_type: {
        value: "none",
        error: "",
    },
    registration_data: {
        value: "",
        error: "",
        rules: {
            type:
                // eslint-disable-next-line
                registration_data,
            email_message: "A valid email address is required",
            url_message: "A valid URL is required",
        },
    },
    hero: {
        value: "",
        error: "",
        rules: {
            required: true,
        },
    },
    content: {
        value: "",
        error: "",
    },
});

useValidation(formData);

const loadData = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    if (route.params.id) {
        try {
            let res = await axios.get("events/" + route.params.id);
            if (!res.data.event) {
                throw new Error("The server is currently not available");
            }

            formData.title.value = res.data.event.title;
            formData.location.value = res.data.event.location;
            formData.address.value = res.data.event.address
                ? res.data.event.address
                : "";
            formData.start_at.value = timestampUtcToLocal(
                res.data.event.start_at
            );
            formData.end_at.value = timestampUtcToLocal(res.data.event.end_at);
            formData.status.value = res.data.event.status;
            formData.publish_at.value = timestampUtcToLocal(
                res.data.event.publish_at
            );
            formData.registration_type.value = res.data.event.registration_type;
            formData.registration_data.value = res.data.event.registration_data;
            formData.content.value = res.data.event.content
                ? res.data.event.content
                : "";
            formData.hero.value = res.data.event.hero;
        } catch (err) {
            pageError.value = err.response.status;
        }
    }

    formLoading.value = false;
};

const submit = async () => {
    console.log(formData.end_at.value);

    try {
        if (isValidated(formData)) {
            let data = {
                title: formData.title.value,
                location: formData.location.value,
                address: formData.address.value,
                start_at: timestampLocalToUtc(formData.start_at.value),
                end_at: timestampLocalToUtc(formData.end_at.value),
                status: formData.status.value,
                publish_at:
                    formData.publish_at.value == ""
                        ? ""
                        : timestampLocalToUtc(formData.publish_at.value),
                registration_type: formData.registration_type.value,
                registration_data: formData.registration_data.value,
                content: formData.content.value,
                hero: formData.hero.value,
            };

            let res = {};
            if (route.params.id) {
                res = await axios.put(`events/${route.params.id}`, data);
            } else {
                console.log(data);
                res = await axios.post(`events`, data);
            }

            console.log(res);
            formMessage.type = "success";
            formMessage.message = "Your details have been updated";
        }
    } catch (err) {
        console.log(err);
        formMessage.icon = "";
        formMessage.type = "error";
        formMessage.message = "";
        restParseErrors(formData, [formMessage, "message"], err);
    }

    window.scrollTo({
        top: 0,
        left: 0,
        behavior: "smooth",
    });
};

const createStorageKey = (file) => {
    var date = new Date();
    var day = date.toISOString().slice(0, 10);
    var name = date.getTime() + "-" + file.name;
    return ["tmp", day, name].join("/");
};

const attachmentAdd = async (event) => {
    if (event.attachment.file) {
        const key = createStorageKey(event.attachment.file);

        var fileFormData = new FormData();
        fileFormData.append("key", key);
        fileFormData.append("Content-Type", event.attachment.file.type);
        fileFormData.append("file", event.attachment.file);

        try {
            let res = await axios.post("media", fileFormData, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                onUploadProgress: (progressEvent) =>
                    event.attachment.setUploadProgress(
                        (progressEvent.loaded * progressEvent.total) / 100
                    ),
            });

            event.attachment.setAttributes({
                url: res.data.media.url,
                href: res.data.media.url,
            });
        } catch (err) {
            event.preventDefault();
            alert(
                err.response?.data?.message ||
                    "An unexpected server error occurred"
            );
        }
    }
};

loadData();
</script>
