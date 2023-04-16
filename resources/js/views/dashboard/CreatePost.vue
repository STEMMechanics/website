<template>
    <SMPage>
        <SMForm v-model="form" @submit="handleSubmit">
            <SMRow>
                <SMInput control="title" />
            </SMRow>
            <SMRow>
                <SMEditor
                    id="content"
                    v-model="form.content.value"
                    @file-accept="fileAccept"
                    @attachment-add="attachmentAdd" />
            </SMRow>
            <SMRow>
                <SMButton type="submit" label="Save" />
            </SMRow>
        </SMForm>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive } from "vue";
import { useRoute } from "vue-router";
import SMButton from "../../components/SMButton.vue";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../depreciated/SMInput-old.vue";

import { api } from "../../helpers/api";
import { Form, FormControl } from "../../helpers/form";
import { And, Min, Required } from "../../helpers/validate";

const route = useRoute();
let form = reactive(
    Form({
        title: FormControl("", And([Required(), Min(2)])),
        content: FormControl("", Required()),
    })
);

// const getPostById = async () => {
//     try {
//         if (isValidated(formData)) {
//             let res = await axios.get("posts/" + route.params.id);

//             formData.title.value = res.data.title;
//             formData.content.value = res.data.content;
//         }
//     } catch (err) {
//         console.log(err);
//         formMessage.icon = "";
//         formMessage.type = "error";
//         formMessage.message = "";
//         restParseErrors(formData, [formMessage, "message"], err);
//     }
// };

const handleSubmit = async () => {
    try {
        await api.post({
            url: "/posts",
            body: {
                title: form.title.value,
                content: form.content.value,
            },
        });

        form.message("The post has been saved", "success");
    } catch (error) {
        form.apiError(error);
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
        } catch (err) {
            event.preventDefault();
        }
    }
};
</script>
