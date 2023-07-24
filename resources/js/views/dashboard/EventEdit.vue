<template>
    <SMPageStatus v-if="!userHasPermission('admin/events')" :status="403" />
    <template v-else>
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-event-list' }"
            back-title="Back to Events" />
        <div class="max-w-7xl mx-auto mt-8 px-8">
            <SMLoading v-if="pageLoading" />
            <SMForm
                v-else
                :model-value="form"
                @submit="handleSubmit"
                @failed-validation="handleFailValidation">
                <div class="flex gap-4 mb-8">
                    <SMInput control="title" />
                    <SMDropdown
                        control="location"
                        type="select"
                        :options="{
                            online: 'Online',
                            physical: 'Physical',
                        }" />
                </div>
                <div
                    class="flex flex-col md:flex-row gap-4"
                    v-if="form.controls.location.value !== 'online'">
                    <SMInput class="mb-4" control="address" />
                    <SMInput class="mb-4" control="location_url" />
                </div>
                <div class="flex flex-col md:flex-row gap-4">
                    <SMInput
                        type="datetime"
                        class="mb-4"
                        control="start_at"
                        label="Start Date/Time" />
                    <SMInput
                        type="datetime"
                        class="mb-4"
                        control="end_at"
                        label="End Date/Time" />
                </div>
                <div class="flex flex-col md:flex-row gap-4">
                    <SMInput
                        type="datetime"
                        class="mb-4"
                        control="publish_at"
                        label="Publish Date/Time" />
                    <SMDropdown
                        type="select"
                        class="mb-4"
                        control="status"
                        :options="{
                            draft: 'Draft',
                            soon: 'Opening Soon',
                            open: 'Open',
                            scheduled: 'Scheduled',
                            closed: 'Closed',
                            cancelled: 'Cancelled',
                        }" />
                </div>
                <div class="flex flex-col md:flex-row gap-4">
                    <SMInput class="mb-4" control="price"
                        >Leave blank to hide from public. Also supports TBD and
                        TBC.</SMInput
                    >
                    <SMInput class="mb-4" control="ages"
                        >Leave blank to hide from public.</SMInput
                    >
                </div>
                <div class="flex flex-col md:flex-row gap-4">
                    <SMDropdown
                        type="select"
                        class="mb-4"
                        control="registration_type"
                        label="Registration"
                        :options="{
                            none: 'None',
                            email: 'Email',
                            link: 'Link',
                            message: 'Message',
                        }" />
                    <SMInput
                        v-if="registration_data?.visible"
                        class="mb-4"
                        control="registration_data"
                        :label="registration_data?.title"
                        :type="registration_data?.type" />
                </div>
                <div class="mb-8">
                    <SMSelectImage
                        control="hero"
                        label="Hero image"
                        allow-upload />
                </div>
                <SMEditor
                    class="mb-8"
                    v-model:model-value="form.controls.content.value" />
                <SMAttachments
                    class="mb-8"
                    show-editor
                    v-model:model-value="attachments" />
                <div class="flex flex-justify-end">
                    <input
                        type="submit"
                        class="font-medium px-6 py-3.1 rounded-2 hover:shadow-md text-lg transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        value="Save" />
                </div>
            </SMForm>
        </div>
    </template>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMEditor from "../../components/SMEditor.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import { Form, FormControl } from "../../helpers/form";
import {
    And,
    Custom,
    DateTime,
    Email,
    Min,
    Required,
    Url,
} from "../../helpers/validate";
import SMAttachments from "../../components/SMAttachments.vue";
import SMForm from "../../components/SMForm.vue";
import { EventResponse } from "../../helpers/api.types";
import { useToastStore } from "../../store/ToastStore";
import SMMastHead from "../../components/SMMastHead.vue";
import SMLoading from "../../components/SMLoading.vue";
import SMPageStatus from "../../components/SMPageStatus.vue";
import { userHasPermission } from "../../helpers/utils";
import SMDropdown from "../../components/SMDropdown.vue";
import SMSelectImage from "../../components/SMSelectImage.vue";

const route = useRoute();
const router = useRouter();

const pageError = ref(200);
const pageLoading = ref(false);
const pageHeading = route.params.id ? "Edit Event" : "Create Event";

const attachments = ref([]);

const address_data = computed(() => {
    let data = {
        title: "",
        required: false,
    };

    if (form?.controls.location.value === "online") {
        data.required = false;
    } else if (form?.controls.location.value === "physical") {
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

    if (form?.controls.registration_type.value === "email") {
        data.visible = true;
        data.title = "Registration email";
        data.type = "email";
    } else if (form?.controls.registration_type.value === "link") {
        data.visible = true;
        data.title = "Registration URL";
        data.type = "url";
    } else if (form?.controls.registration_type.value === "message") {
        data.visible = true;
        data.title = "Registration message";
        data.type = "text";
    }

    return data;
});

let form = reactive(
    Form({
        title: FormControl("", And([Required(), Min(6)])),
        location: FormControl("online"),
        address: FormControl(
            "",
            Custom(async (value) => {
                return address_data?.value.required && value.length == 0
                    ? "A venue address is required"
                    : true;
            }),
        ),
        location_url: FormControl("", Url()),
        start_at: FormControl("", And([Required(), DateTime()])),
        end_at: FormControl(
            "",
            And([
                Required(),
                DateTime({
                    after: (v) => {
                        return form.controls.start_at.value;
                    },
                    invalidAfterMessage:
                        "The ending date/time must be after the starting date/time.",
                }),
            ]),
        ),
        publish_at: FormControl(
            route.params.id ? "" : new SMDate("now").format("d/M/yy h:mm aa"),
            DateTime(),
        ),
        status: FormControl("draft"),
        registration_type: FormControl("none"),
        registration_data: FormControl(
            "",
            Custom(async (v) => {
                let validationResult = {
                    valid: true,
                    invalidMessages: [""],
                };

                if (form.controls.registration_type.value == "email") {
                    validationResult = await Email().validate(v);
                } else if (form.controls.registration_type.value == "url") {
                    validationResult = await Url().validate(v);
                }

                if (!validationResult.valid) {
                    return validationResult.invalidMessages[0];
                }

                return true;
            }),
        ),
        hero: FormControl("", Required()),
        content: FormControl(),
        price: FormControl(),
        ages: FormControl(),
    }),
);

const loadData = async () => {
    if (route.params.id) {
        try {
            pageLoading.value = true;

            const result = await api.get({
                url: "/events/{id}",
                params: { id: route.params.id },
            });
            const data = result.data as EventResponse;

            if (!data || !data.event) {
                throw new Error("The server is currently not available");
            }

            form.controls.title.value = data.event.title;
            form.controls.location.value = data.event.location;
            form.controls.location_url.value = data.event.location_url;
            form.controls.address.value = data.event.address
                ? data.event.address
                : "";
            form.controls.start_at.value = new SMDate(data.event.start_at, {
                format: "ymd",
                utc: true,
            }).format("dd/MM/yyyy h:mm aa");
            form.controls.end_at.value = new SMDate(data.event.end_at, {
                format: "ymd",
                utc: true,
            }).format("dd/MM/yyyy h:mm aa");
            form.controls.status.value = data.event.status;
            form.controls.publish_at.value = new SMDate(data.event.publish_at, {
                format: "ymd",
                utc: true,
            }).format("dd/MM/yyyy h:mm aa");
            form.controls.registration_type.value =
                data.event.registration_type;
            form.controls.registration_data.value =
                data.event.registration_data;
            form.controls.content.value = data.event.content
                ? data.event.content
                : "";
            form.controls.hero.value = data.event.hero;
            form.controls.price.value = data.event.price;
            form.controls.ages.value = data.event.ages;

            attachments.value = data.event.attachments;
        } catch (err) {
            pageError.value = err.status;
        } finally {
            pageLoading.value = false;
        }
    }
};

const handleSubmit = async () => {
    try {
        let data = {
            title: form.controls.title.value,
            location: form.controls.location.value,
            location_url: form.controls.location_url.value,
            address: form.controls.address.value,
            start_at: new SMDate(form.controls.start_at.value, {
                format: "dmy",
            }).format("yyyy/MM/dd HH:mm:ss", { utc: true }),
            end_at: new SMDate(form.controls.end_at.value, {
                format: "dmy",
            }).format("yyyy/MM/dd HH:mm:ss", { utc: true }),
            status: form.controls.status.value,
            publish_at:
                form.controls.publish_at.value == ""
                    ? ""
                    : new SMDate(form.controls.publish_at.value, {
                          format: "dmy",
                      }).format("yyyy/MM/dd HH:mm:ss", { utc: true }),
            registration_type: form.controls.registration_type.value,
            registration_data: form.controls.registration_data.value,
            content: form.controls.content.value,
            hero: form.controls.hero.value.id,
            price: form.controls.price.value,
            ages: form.controls.ages.value,
            attachments: attachments.value.map((item) => item.id),
        };

        let event_id = "";

        if (route.params.id) {
            event_id = route.params.id as string;
            await api.put({
                url: "/events/{id}",
                params: {
                    id: route.params.id,
                },
                body: data,
            });
        } else {
            let result = await api.post({
                url: "/events",
                body: data,
            });

            if (result.data) {
                const data = result.data as EventResponse;
                event_id = data.event.id;
            }
        }

        useToastStore().addToast({
            title: route.params.id ? "Event Updated" : "Event Created",
            content: route.params.id
                ? "The event has been updated."
                : "The event has been created.",
            type: "success",
        });

        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get("return");
        if (returnUrl) {
            router.push(decodeURIComponent(returnUrl));
        } else {
            router.push({ name: "dashboard-event-list" });
        }
    } catch (error) {
        useToastStore().addToast({
            title: "Server error",
            content: "An error occurred saving the event.",
            type: "danger",
        });
    }
};

const handleFailValidation = () => {
    useToastStore().addToast({
        title: "Save Error",
        content:
            "There are some errors in the form. Fix these before continuing.",
        type: "danger",
    });
};

loadData();
</script>

<style lang="scss"></style>
