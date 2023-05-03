<template>
    <SMPage :page-error="pageError" permission="admin/events">
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-event-list' }"
            back-title="Back to Events" />
        <SMContainer class="flex-grow-1">
            <SMLoading v-if="pageLoading" large />
            <SMForm
                v-else
                :model-value="form"
                @submit="handleSubmit"
                @failed-validation="handleFailValidation">
                <SMRow>
                    <SMColumn><SMInput control="title" /></SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            control="location"
                            type="select"
                            :options="{
                                online: 'Online',
                                physical: 'Physical',
                            }" />
                    </SMColumn>
                    <SMColumn>
                        <SMInput
                            v-if="form.controls.location.value !== 'online'"
                            control="address"
                    /></SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            type="datetime"
                            control="start_at"
                            label="Start Date/Time" />
                    </SMColumn>
                    <SMColumn>
                        <SMInput
                            type="datetime"
                            control="end_at"
                            label="End Date/Time" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            type="datetime"
                            control="publish_at"
                            label="Publish Date/Time" />
                    </SMColumn>
                    <SMColumn>
                        <SMInput
                            type="select"
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
                        <SMInput control="price"
                            >Leave blank to hide from public.</SMInput
                        >
                    </SMColumn>
                    <SMColumn>
                        <SMInput control="ages"
                            >Leave blank to hide from public.</SMInput
                        >
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            type="select"
                            control="registration_type"
                            label="Registration"
                            :options="{
                                none: 'None',
                                email: 'Email',
                                link: 'Link',
                                message: 'Message',
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
                            v-model:model-value="form.controls.content.value" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInputAttachments :model-value="attachments" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMButtonRow>
                        <template #right>
                            <SMButton type="submit" label="Save" />
                        </template>
                    </SMButtonRow>
                </SMRow>
            </SMForm>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMButton from "../../components/SMButton.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMButtonRow from "../../components/SMButtonRow.vue";
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
import SMInputAttachments from "../../components/SMInputAttachments.vue";
import SMForm from "../../components/SMForm.vue";
import { EventResponse } from "../../helpers/api.types";
import { useToastStore } from "../../store/ToastStore";
import SMMastHead from "../../components/SMMastHead.vue";
import SMLoading from "../../components/SMLoading.vue";

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
            })
        ),
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
            ])
        ),
        publish_at: FormControl(
            route.params.id ? "" : new SMDate("now").format("d/M/yy h:mm aa"),
            DateTime()
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
            })
        ),
        hero: FormControl("", Required()),
        content: FormControl(),
        price: FormControl(),
        ages: FormControl(),
    })
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

            attachments.value = (data.event.attachments || []).map(function (
                attachment
            ) {
                return attachment.id.toString();
            });
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

        await api.put({
            url: `/events/${event_id}/attachments`,
            body: {
                attachments: attachments.value,
            },
        });

        useToastStore().addToast({
            title: route.params.id ? "Event Updated" : "Event Created",
            content: route.params.id
                ? "The event has been updated."
                : "The event has been created.",
            type: "success",
        });

        router.push({ name: "dashboard-event-list" });
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
