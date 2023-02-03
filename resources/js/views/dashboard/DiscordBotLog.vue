<template>
    <SMContainer :loading="formLoading" permission="logs/discord">
        <h1>Discord Bot Log</h1>
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message" />
        <code v-if="logContent.value.length > 0">{{ logContent }}</code>
        <SMButton label="Reload" @click="loadData" />
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import SMButton from "../../components/SMButton.vue";
import SMMessage from "../../components/SMMessage.vue";
import axios from "axios";

let formLoading = ref(false);
let logContent = ref("");
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const loadData = async () => {
    formMessage.icon = "";
    formMessage.type = "error";
    formMessage.message = "";

    try {
        formLoading.value = true;
        let res = await axios.get(`logs/discord`);

        //console.log(res.data.log.split(/2023-02-03T00:23:40: /));

        logContent.value = res.data.log;
        if (logContent.value.length === 0) {
            formMessage.message = "Log file is empty";
        }
    } catch (err) {
        formMessage.message = "Could not load log from server";
    }

    formLoading.value = false;
};

loadData();
</script>
