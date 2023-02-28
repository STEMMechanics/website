<template>
    <SMPage
        :page-error="pageError"
        permission="admin/events"
        class="sm-page-event-edit">
        <template #container>
            <h1>{{ page_title }}</h1>
            <SMForm :model-value="form" @submit="handleSubmit">
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
                    <SMColumn
                        ><SMInput
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
                        <SMInput
                            type="select"
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
                            :model-value="form.controls.content.value"
                            :mime-types="[
                                'image/png',
                                'image/jpeg',
                                'image/gif',
                            ]" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInputAttachments :model-value="attachments" />
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
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import SMButton from "../../components/SMButton.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
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

const route = useRoute();
const page_title = route.params.id ? "Edit Event" : "Create New Event";
const pageError = ref(200);
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
    }

    return data;
});

const form = reactive(
    Form({
        title: FormControl("", And([Required(), Min(6)])),
        location: FormControl("online"),
        address: FormControl(
            "",
            Custom(async (value) => {
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
                        return form.controls.start_at.value;
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
            Custom(async (v) => {
                let validationResult = {
                    valid: true,
                    invalidMessages: [""],
                };

                if (registration_data.value.type == "email") {
                    validationResult = await Email().validate(v);
                } else if (registration_data.value.type == "url") {
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

            form.controls.title.value = res.data.event.title;
            form.controls.location.value = res.data.event.location;
            form.controls.address.value = res.data.event.address
                ? res.data.event.address
                : "";
            form.controls.start_at.value = new SMDate(res.data.event.start_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");
            form.controls.end_at.value = new SMDate(res.data.event.end_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");
            form.controls.status.value = res.data.event.status;
            form.controls.publish_at.value = new SMDate(
                res.data.event.publish_at,
                {
                    format: "ymd",
                    utc: true,
                }
            ).format("yyyy/MM/dd HH:mm:ss");
            form.controls.registration_type.value =
                res.data.event.registration_type;
            form.controls.registration_data.value =
                res.data.event.registration_data;
            form.controls.content.value = res.data.event.content
                ? res.data.event.content
                : "";
            form.controls.hero.value = res.data.event.hero;
        } catch (err) {
            pageError.value = err.response.status;
        }
    }

    form.loading(false);
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
            hero: form.controls.hero.value,
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

loadData();
</script>

<style lang="scss">
.sm-page-event-edit {
    background-color: #f8f8f8;
}
</style>
