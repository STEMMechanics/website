<template>
    <SMPage :page-error="pageError" permission="admin/events">
        <SMRow>
            <SMDialog :loading="formLoading">
                <h1>{{ page_title }}</h1>
                <SMForm :model-value="form" @submit="handleSubmit">
                    <SMRow>
                        <SMColumn><SMInput control="title" /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                control="location"
                                :options="{
                                    online: 'Online',
                                    physical: 'Physical',
                                }" />
                        </SMColumn>
                        <SMColumn
                            ><SMInput
                                v-if="form.location.value !== 'online'"
                                control="address"
                        /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMDatepicker
                                control="start_at"
                                label="Start Date/Time"></SMDatepicker>
                        </SMColumn>
                        <SMColumn>
                            <SMDatepicker
                                control="end_at"
                                label="End Date/Time"></SMDatepicker>
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMDatepicker
                                control="publish_at"
                                label="Publish Date/Time"></SMDatepicker>
                        </SMColumn>
                        <SMColumn>
                            <SMInput
                                control="status"
                                :options="{
                                    draft: 'Draft',
                                    soon: 'Opening Soon',
                                    open: 'Open',
                                    closed: 'Closed',
                                    cancelled: 'Cancelled',
                                }" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                control="registration_type"
                                label="Registration"
                                :options="{
                                    none: 'None',
                                    email: 'Email',
                                    link: 'Link',
                                }" />
                        </SMColumn>
                        <SMColumn>
                            <SMInput
                                v-if="registration_data?.visible"
                                control="registration_data"
                                :label="registration_data?.title"
                                :type="registration_data?.type" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                control="hero"
                                type="media"
                                label="Hero image" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMEditor
                                v-model:srcContent="form.content.value"
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
                </SMForm>
            </SMDialog>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import SMButton from "../../components/SMButton.vue";
import SMDatepicker from "../../components/SMDatePicker.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import { FormControl } from "../../helpers/form";
import {
    And,
    Custom,
    DateTime,
    Email,
    Min,
    Required,
    Url,
} from "../../helpers/validate";

import SMForm from "../../components/SMForm.vue";

const route = useRoute();
const page_title = route.params.id ? "Edit Event" : "Create New Event";
const pageError = ref(200);

const address_data = computed(() => {
    let data = {
        title: "",
        required: false,
    };

    if (form?.location.value === "online") {
        data.required = false;
    } else if (form?.location.value === "physical") {
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

    if (form?.registration_type.value === "email") {
        data.visible = true;
        data.title = "Registration email";
        data.type = "email";
    } else if (form?.registration_type.value === "link") {
        data.visible = true;
        data.title = "Registration URL";
        data.type = "url";
    }

    return data;
});

const form = reactive(
    Form({
        title: FormControl("", And([Required(), Min(6)])),
        location: FormControl("online"),
        address: FormControl(
            "",
            Custom((value) => {
                return address_data?.value.required && value.length == 0
                    ? "A venue address is required"
                    : false;
            })
        ),
        start_at: FormControl("", And([Required(), DateTime()])),
        end_at: FormControl(
            "",
            And([
                Required(),
                DateTime({
                    after: (v) => {
                        return form.start_at.value;
                    },
                    invalidAfterMessage:
                        "The ending date/time must be after the starting date/time.",
                }),
            ])
        ),
        publish_at: FormControl("", DateTime()),
        status: FormControl(),
        registration_type: FormControl("none"),
        registration_data: FormControl(
            "",
            Custom((v) => {
                let validationResult = {
                    valid: true,
                    invalidMessages: [""],
                };

                if (registration_data.value.type == "email") {
                    validationResult = Email().validate(v);
                } else if (registration_data.value.type == "url") {
                    validationResult = Url().validate(v);
                }

                if (!validationResult.valid) {
                    return validationResult.invalidMessages[0];
                }

                return true;
            })
        ),
        hero: FormControl("", Required()),
        content: FormControl(),
    })
);

const loadData = async () => {
    form.loading(true);

    if (route.params.id) {
        try {
            let res = await api.get("/events/" + route.params.id);
            if (!res.data.event) {
                throw new Error("The server is currently not available");
            }

            form.title.value = res.data.event.title;
            form.location.value = res.data.event.location;
            form.address.value = res.data.event.address
                ? res.data.event.address
                : "";
            form.start_at.value = new SMDate(res.data.event.start_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");
            form.end_at.value = new SMDate(res.data.event.end_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");
            form.status.value = res.data.event.status;
            form.publish_at.value = new SMDate(res.data.event.publish_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");
            form.registration_type.value = res.data.event.registration_type;
            form.registration_data.value = res.data.event.registration_data;
            form.content.value = res.data.event.content
                ? res.data.event.content
                : "";
            form.hero.value = res.data.event.hero;
        } catch (err) {
            pageError.value = err.response.status;
        }
    }

    form.loading(false);
};

const handleSubmit = async () => {
    try {
        let data = {
            title: form.title.value,
            location: form.location.value,
            address: form.address.value,
            start_at: new SMDate(form.start_at.value, { format: "dmy" }).format(
                "yyyy/MM/dd HH:mm:ss",
                { utc: true }
            ),
            end_at: new SMDate(form.end_at.value, { format: "dmy" }).format(
                "yyyy/MM/dd HH:mm:ss",
                { utc: true }
            ),
            status: form.status.value,
            publish_at:
                form.publish_at.value == ""
                    ? ""
                    : new SMDate(form.publish_at.value, {
                          format: "dmy",
                      }).format("yyyy/MM/dd HH:mm:ss", { utc: true }),
            registration_type: form.registration_type.value,
            registration_data: form.registration_data.value,
            content: form.content.value,
            hero: form.hero.value,
        };

        if (route.params.id) {
            await api.put({
                url: `/events/${route.params.id}`,
                body: data,
            });
        } else {
            await api.post({
                url: "events",
                body: data,
            });
        }

        form.message("Your details have been updated", "success");
    } catch (error) {
        form.apiError(error);
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
