<template>
    <SMPage permission="logs/discord">
        <SMMastHead
            title="Discord"
            :back-link="{ name: 'dashboard' }"
            back-title="Back to Dashboard" />
        <SMContainer class="flex-grow-1">
            <SMRow>
                <SMColumn>
                    <SMTabGroup>
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
                </SMColumn>
            </SMRow>
            <button type="button" @click="loadData">Reload Logs</button>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { ref } from "vue";
import SMTab from "../../components/SMTab.vue";
import SMTabGroup from "../../components/SMTabGroup.vue";
import { api } from "../../helpers/api";
import { LogsDiscordResponse } from "../../helpers/api.types";
import { useToastStore } from "../../store/ToastStore";
import SMMastHead from "../../components/SMMastHead.vue";

let formLoading = ref(false);
let logOutputContent = ref("");
let logErrorContent = ref("");

const loadData = async () => {
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
        useToastStore().addToast({
            title: "Server Error",
            content: "Could not load logs from server",
            type: "danger",
        });
    } finally {
        formLoading.value = false;
    }
};

loadData();
</script>
