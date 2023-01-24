<template>
    <SMContainer>
        <SMMessage
            v-if="formMessage.message"
            :type="formMessage.type"
            :message="formMessage.message"
            :icon="formMessage.icon" />
        <form @submit.prevent="submit">
            <SMRow>
                <SMInput
                    v-model="formData.title.value"
                    label="Title"
                    required
                    :error="formData.title.error"
                    @blur="fieldValidate(formData.title)" />
            </SMRow>
            <SMRow>
                <SMEditor
                    id="content"
                    v-model="formData.content.value"
                    @file-accept="fileAccept"
                    @attachment-add="attachmentAdd" />
            </SMRow>
            <SMRow>
                <SMButton type="submit" label="Save" />
            </SMRow>
        </form>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import DEditor from "../../components/SMEditor.vue";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMMessage from "../../components/SMMessage.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../../helpers/validation";
import { useUserStore } from "@/store/UserStore";
import { useRoute } from "vue-router";
import { createTemplateLiteral } from "@vue/compiler-core";

const route = useRoute();
const userStore = useUserStore();
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});
const formData = reactive({
    title: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A first name is needed",
            min: 2,
            min_message: "Your first name should be at least 2 letters long",
        },
    },
    content: {
        value: "<div>Hello <strong>People</strong> persons!</div>",
        error: "",
        rules: {
            required: true,
            required_message: "A last name is needed",
            min: 2,
            min_message: "Your last name should be at least 2 letters long",
        },
    },
});

useValidation(formData);

const getPostById = async () => {
    try {
        if (isValidated(formData)) {
            let res = await axios.get("posts/" + route.params.id);

            formData.title.value = res.data.title;
            formData.content.value = res.data.content;
        }
    } catch (err) {
        console.log(err);
        formMessage.icon = "";
        formMessage.type = "error";
        formMessage.message = "";
        restParseErrors(formData, [formMessage, "message"], err);
    }
};

const submit = async () => {
    try {
        if (isValidated(formData)) {
            let res = await axios.post("posts", {
                title: formData.title.value,
                content: formData.content.value,
            });

            console.log(ref);
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
};

const fileAccept = (event) => {
    if (event.file.type != "image/png") {
        event.preventDefault();
    }
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
            let res = await axios.post("upload", fileFormData, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                onUploadProgress: (progressEvent) =>
                    event.attachment.setUploadProgress(
                        (progressEvent.loaded * progressEvent.total) / 100
                    ),
            });

            event.attachment.setAttributes({
                url: res.data.url,
                href: res.data.url,
            });

            console.log(res);
        } catch (err) {
            event.preventDefault();
            console.log(err);
        }
    }
};
</script>

<style lang="scss">
// .dialog {
//     flex-direction: column;
//     margin: 0 auto;
//     max-width: 600px;
// }

// .buttonFooter {
//     flex-direction: row;
// }

// @media screen and (max-width: 768px) {
//     .buttonFooter {
//         flex-direction: column-reverse;
//     }
// }
</style>
