<template>
    <SMContainer :loading="formLoading" permission="logs/discord">
        <h1>Discord Bot Logs</h1>
        <SMMessage
            v-if="message.message"
            :icon="message.icon"
            :type="message.type"
            :message="message.message" />
        <SMTabGroup v-if="!message.message">
            <SMTab label="Output">
                <code v-if="logOutputContent.length > 0">{{
                    logOutputContent
                }}</code>
            </SMTab>
            <SMTab label="Errors">
                <code v-if="logErrorContent.length > 0">{{
                    logErrorContent
                }}</code>
            </SMTab>
        </SMTabGroup>
        <SMButton
            v-if="!message.message"
            label="Reload Logs"
            @click="loadData" />
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import SMButton from "../../components/SMButton.vue";
import SMTabGroup from "../../components/SMTabGroup.vue";
import SMTab from "../../components/SMTab.vue";
import SMMessage from "../../components/SMMessage.vue";
import axios from "axios";

let formLoading = ref(false);
let logOutputContent = ref("");
let logErrorContent = ref("");
const message = reactive({
    icon: "",
    type: "",
    message: "",
});

const loadData = async () => {
    message.icon = "";
    message.type = "error";
    message.message = "";

    try {
        formLoading.value = true;
        let res = await axios.get(`logs/discord`);

        logOutputContent.value = res.data.log.output;
        if (logOutputContent.value.length === 0) {
            logOutputContent.value = "Log file is empty";
        }

        logErrorContent.value = res.data.log.errors;
        if (logErrorContent.value.length === 0) {
            logErrorContent.value = "Log file is empty";
        }
    } catch (err) {
        message.message = "Could not load logs from server";
    }

    formLoading.value = false;
};

loadData();
</script>
