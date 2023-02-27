<template>
    <SMPage class="sm-workshop-list">
        <template #container>
            <h1>Workshops</h1>
            <SMToolbar>
                <SMInput
                    v-model="filterKeywords"
                    label="Keywords"
                    @change="handleFilter" />
                <SMInput
                    v-model="filterLocation"
                    label="Location"
                    @change="handleFilter" />
                <SMInput
                    v-model="filterDateRange"
                    type="daterange"
                    label="Date Range"
                    :feedback-invalid="dateRangeError"
                    @change="handleFilter" />
            </SMToolbar>
            <SMMessage
                v-if="formMessage"
                icon="alert-circle-outline"
                type="error"
                :message="formMessage"
                class="mt-5" />
            <SMPanelList
                :loading="loading"
                :not-found="events.length == 0"
                not-found-text="No workshops found">
                <SMPanel
                    v-for="event in events"
                    :key="event.id"
                    :to="{ name: 'event-view', params: { id: event.id } }"
                    :title="event.title"
                    :image="event.hero"
                    :show-time="true"
                    :date="event.start_at"
                    :end-date="event.end_at"
                    :date-in-image="true"
                    :location="
                        event.location == 'online'
                            ? 'Online Event'
                            : event.address
                    "></SMPanel>
            </SMPanelList>
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import SMInput from "../components/SMInput.vue";
import SMMessage from "../components/SMMessage.vue";
import SMPanel from "../components/SMPanel.vue";
import SMPanelList from "../components/SMPanelList.vue";
import SMToolbar from "../components/SMToolbar.vue";
import { api } from "../helpers/api";
import { Event, EventCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";

const loading = ref(true);
let events: Event[] = reactive([]);
const dateRangeError = ref("");

const formMessage = ref("");

const filterKeywords = ref("");
const filterLocation = ref("");
const filterDateRange = ref("");

/**
 * Load page data.
 */
const handleLoad = async () => {
    let query = {};
    query["limit"] = 10;

    if (filterKeywords.value && filterKeywords.value.length > 0) {
        query["q"] = filterKeywords.value;
    }
    if (filterLocation.value && filterLocation.value.length > 0) {
        query["qlocation"] = filterLocation.value;
    }
    if (filterDateRange.value && filterDateRange.value.length > 0) {
        let error = false;
        const filterDates = filterDateRange.value
            .split(/ *- */)
            .map((dateString) => {
                const date = new SMDate(dateString).format("yyyy/MM/dd");

                if (date.length == 0) {
                    error = true;
                }

                return date;
            });

        if (!error) {
            if (filterDates.length == 1) {
                query["start_at"] = `>=${filterDates[0]}`;
            } else if (filterDates.length >= 2) {
                query["start_at"] = `${filterDates[0]}<>${filterDates[1]}`;
            }

            dateRangeError.value = "";
        } else {
            dateRangeError.value = "Invalid date range";
            return;
        }
    } else {
        dateRangeError.value = "";
    }

    loading.value = true;
    formMessage.value = "";
    events = [];

    if (Object.keys(query).length == 1 && Object.keys(query)[0] == "limit") {
        query["end_at"] =
            ">" +
            new SMDate("now").format("yyyy/MM/dd HH:mm:ss", { utc: true });
    }

    api.get({
        url: "/events",
        params: query,
    })
        .then((result) => {
            const data = result.data as EventCollection;

            if (data && data.events) {
                events = data.events;

                events.forEach((item) => {
                    item.start_at = new SMDate(item.start_at, {
                        format: "yyyy-MM-dd HH:mm:ss",
                        utc: true,
                    }).format("yyyy-MM-dd HH:mm:ss");

                    item.end_at = new SMDate(item.end_at, {
                        format: "yyyy-MM-dd HH:mm:ss",
                        utc: true,
                    }).format("yyyy-MM-dd HH:mm:ss");
                });
            }
        })
        .catch((error) => {
            if (error.status != 404) {
                formMessage.value =
                    error.response?.data?.message ||
                    "Could not load any events from the server.";
            }
        })
        .finally(() => {
            loading.value = false;
        });
};

const handleFilter = async () => {
    handleLoad();
};

handleLoad();
</script>

<style lang="scss">
.workshop-list {
    background-color: #f8f8f8;

    .toolbar {
        display: flex;
        flex-direction: row;
        flex: 1;

        & > * {
            padding-left: map-get($spacer, 1);
            padding-right: map-get($spacer, 1);

            &:first-child {
                padding-left: 0;
            }

            &:last-child {
                padding-right: 0;
            }
        }
    }
}

@media screen and (max-width: 768px) {
    .sm-workshop-list .toolbar {
        flex-direction: column;

        & > * {
            padding-left: 0;
            padding-right: 0;
        }
    }
}
</style>
