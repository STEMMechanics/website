<template>
    <SMContainer class="mx-auto workshop-list">
        <h1>Workshops</h1>
        <div class="toolbar">
            <SMInput
                v-model="filterKeywords"
                placeholder="Keywords"
                @change="handleFilter"></SMInput>
            <SMInput
                v-model="filterLocation"
                placeholder="Location"
                @change="handleFilter"></SMInput>
            <SMDatePicker
                v-model="filterDateRange"
                :range="true"
                placeholder="Date Range"
                @update:model-value="handleFilter"></SMDatePicker>
        </div>
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message"
            class="mt-5" />
        <SMPanelList
            :loading="loading"
            :not-found="events.value?.length == 0"
            not-found-text="No events found">
            <SMPanel
                v-for="event in events.value"
                :key="event.id"
                :to="{ name: 'workshop-view', params: { id: event.id } }"
                :title="event.title"
                :image="event.hero"
                :show-time="true"
                :date="event.start_at"
                :end-date="event.end_at"
                :date-in-image="true"
                :location="
                    event.location == 'online' ? 'Online Event' : event.address
                "></SMPanel>
        </SMPanelList>
    </SMContainer>
</template>

<script setup lang="ts">
import SMDatePicker from "../components/SMDatePicker.vue";
import SMInput from "../components/SMInput.vue";
import SMMessage from "../components/SMMessage.vue";
import SMPanelList from "../components/SMPanelList.vue";
import SMPanel from "../components/SMPanel.vue";
import { reactive, ref } from "vue";
import axios from "axios";
import { buildUrlQuery } from "../helpers/common";

const loading = ref(true);
const events = reactive([]);

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const filterKeywords = ref("");
const filterLocation = ref("");
const filterDateRange = ref("");

const handleLoad = async () => {
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    events.value = [];

    try {
        let query = {};
        query["limit"] = 10;
        if (filterKeywords.value && filterKeywords.value.length > 0) {
            query["q"] = filterKeywords.value;
        }
        if (filterLocation.value && filterLocation.value.length > 0) {
            query["qlocation"] = filterLocation.value;
        }
        if (filterDateRange.value && Array.isArray(filterDateRange.value)) {
            query["start_at"] =
                filterDateRange.value[0] + "<>" + filterDateRange.value[1];
        }

        const url = buildUrlQuery("events", query);
        let result = await axios.get(url);

        if (result.data.events) {
            events.value = result.data.events;
        }
    } catch (error) {
        if (error.response.status != 404) {
            formMessage.message =
                error.response?.data?.message ||
                "Could not load any events from the server.";
        }
    }

    loading.value = false;
};

const handleFilter = async () => {
    loading.value = true;
    handleLoad();
};

handleLoad();
</script>

<style lang="scss">
.workshop-list .toolbar {
    display: flex;
    flex-direction: row;
}

@media screen and (max-width: 768px) {
    .workshop-list .toolbar {
        flex-direction: column;
    }
}
</style>
