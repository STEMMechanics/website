<template>
    <SMContainer class="dashboard mx-auto workshops">
        <h1>Workshops</h1>
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message"
            class="mt-5" />
        <SMPanelList>
            <SMPanel
                v-for="event in events.value"
                :key="event.id"
                :to="{ name: 'event', params: { slug: event.id } }"
                :title="event.title"
                :date="event.start_at"></SMPanel>
        </SMPanelList>
    </SMContainer>
</template>

<script setup lang="ts">
import SMMessage from "../components/SMMessage.vue";
import SMPanelList from "../components/SMPanelList.vue";
import SMPanel from "../components/SMPanel.vue";
import { reactive } from "vue";
import axios from "axios";

const events = reactive([]);

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const handleLoad = async () => {
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        let result = await axios.get("events?limit=10");
        events.value = result.data.events;
    } catch (error) {
        formMessage.message =
            error.response?.data?.message ||
            "Could not load any events from the server.";
    }
};

handleLoad();
</script>
