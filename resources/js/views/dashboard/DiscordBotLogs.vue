<template>
    <SMPage :loading="formLoading" permission="logs/discord">
        <template #container>
            <h1>Discord Bot Logs</h1>
            <SMMessage v-if="message" type="error" :message="message" />
            <SMTabGroup v-if="!message">
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
            <SMButton v-if="!message" label="Reload Logs" @click="loadData" />
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { ref } from "vue";
import SMButton from "../../components/SMButton.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMTab from "../../components/SMTab.vue";
import SMTabGroup from "../../components/SMTabGroup.vue";
import { api } from "../../helpers/api";
import { LogsDiscordResponse } from "../../helpers/api.types";

let formLoading = ref(false);
let logOutputContent = ref("");
let logErrorContent = ref("");
let message = ref("");

const loadData = async () => {
    message.value = "";

    try {
        formLoading.value = true;
        const result = await api.get({ url: "/logs/discord" });

        const data = result.data as LogsDiscordResponse;
        if (data) {
            logOutputContent.value = data.log.output || "";
            if (logOutputContent.value.length === 0) {
                logOutputContent.value = "Log file is empty";
            }

            logErrorContent.value = data.log.error || "";
            if (logErrorContent.value.length === 0) {
                logErrorContent.value = "Log file is empty";
            }
        }
    } catch (error) {
        message.value = "Could not load logs from server";
    } finally {
        formLoading.value = false;
    }
};

loadData();
</script>
