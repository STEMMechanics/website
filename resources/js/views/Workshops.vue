<template>
    <SMMastHead title="Workshops" />
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col sm:flex-row space-between gap-4 py-8">
            <SMInput
                v-model="filterKeywords"
                label="Keywords"
                @blur="handleFilter"
                @keyup.enter="handleFilter" />
            <SMInput
                v-model="filterLocation"
                label="Location"
                @blur="handleFilter"
                @keyup.enter="handleFilter" />
            <SMInput
                v-model="filterDateRange"
                type="daterange"
                label="Date Range"
                :feedback-invalid="dateRangeError"
                @blur="handleFilter"
                @keyup.enter="handleFilter" />
        </div>
        <SMPagination
            v-if="postsTotal > postsPerPage"
            class="mb-4"
            v-model="postsPage"
            :total="postsTotal"
            :per-page="postsPerPage" />
        <SMLoading v-if="pageLoading" />
        <div
            v-else-if="events.length > 0"
            class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
            <SMEventCard
                v-for="event in events"
                :event="event"
                :key="event.id" />
        </div>
        <div v-else class="py-12 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-24 text-gray-5">
                <path
                    d="M453-280h60v-240h-60v240Zm26.982-314q14.018 0 23.518-9.2T513-626q0-14.45-9.482-24.225-9.483-9.775-23.5-9.775-14.018 0-23.518 9.775T447-626q0 13.6 9.482 22.8 9.483 9.2 23.5 9.2Zm.284 514q-82.734 0-155.5-31.5t-127.266-86q-54.5-54.5-86-127.341Q80-397.681 80-480.5q0-82.819 31.5-155.659Q143-709 197.5-763t127.341-85.5Q397.681-880 480.5-880q82.819 0 155.659 31.5Q709-817 763-763t85.5 127Q880-563 880-480.266q0 82.734-31.5 155.5T763-197.684q-54 54.316-127 86Q563-80 480.266-80Zm.234-60Q622-140 721-239.5t99-241Q820-622 721.188-721 622.375-820 480-820q-141 0-240.5 98.812Q140-622.375 140-480q0 141 99.5 240.5t241 99.5Zm-.5-340Z"
                    fill="currentColor" />
            </svg>
            <p class="text-lg text-gray-5">
                {{ eventsError || "No workshops where found" }}
            </p>
        </div>
        <SMPagination
            v-if="postsTotal > postsPerPage"
            class="mt-4"
            v-model="postsPage"
            :total="postsTotal"
            :per-page="postsPerPage" />
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import SMInput from "../components/SMInput.vue";
import SMPagination from "../components/SMPagination.vue";
import { api } from "../helpers/api";
import { Event, EventCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import SMMastHead from "../components/SMMastHead.vue";
import SMLoading from "../components/SMLoading.vue";
import SMEventCard from "../components/SMEventCard.vue";
import { useRoute, useRouter } from "vue-router";
import { getRouterParam, updateRouterParams } from "../helpers/url";

const pageLoading = ref(true);
let events: Event[] = reactive([]);
const dateRangeError = ref("");
const router = useRouter();

const filterKeywords = ref("");
const filterLocation = ref("");
const filterDateRange = ref("");

let oldFilterValues = {
    keywords: "",
    location: "",
    dateRange: "",
};

const postsPerPage = 18;
let postsPage = ref(1);
let postsTotal = ref(0);
const pageStatus = ref(0);

const eventsError = ref("");

/**
 * Load page data.
 */
const handleLoad = async () => {
    try {
        let query = {};

        /*
        cats, dogs
        (title:"cats, dogs",OR,content:"cats, dogs")

        "cats, dogs", mice
        (title:""cats, dogs", mice",OR,content:"\"cats, dogs\", mice")
        */

        query["filter"] = [];
        if (filterKeywords.value && filterKeywords.value.length > 0) {
            let value = filterKeywords.value.replace(/"/g, '\\"');

            query["filter"].push(`(title:"${value}",OR,content:"${value}")`);
        }
        if (filterLocation.value && filterLocation.value.length > 0) {
            let value = filterLocation.value.replace(/"/g, '\\"');

            query["filter"].push(`(location:"${value}",OR,address:"${value}")`);
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
                    query["start_at"] = `<>${filterDates[0]}|${filterDates[1]}`;
                }

                dateRangeError.value = "";
            } else {
                dateRangeError.value = "Invalid date range";
                return;
            }
        } else {
            dateRangeError.value = "";
        }

        pageLoading.value = true;
        events = [];

        if (query["filter"].length > 0) {
            query["filter"] = query["filter"].join(",AND,");
        } else {
            delete query["filter"];
        }

        if (
            (!filterDateRange.value || filterDateRange.value.length === 0) &&
            (!query["filter"] || query["filter"].length === 0)
        ) {
            const now = new Date();
            const startingDate = new Date(now.setDate(now.getDate() - 8));

            query["end_at"] =
                ">" +
                new SMDate(startingDate).format("yyyy/MM/dd HH:mm:ss", {
                    utc: true,
                });
        }

        query["limit"] = postsPerPage;
        query["page"] = postsPage.value;
        query["sort"] = "start_at";

        updateRouterParams(router, {
            keywords: filterKeywords.value,
            location: filterLocation.value,
            "date-range": filterDateRange.value,
        });

        let result = await api.get({
            url: "/events",
            params: query,
        });

        const data = result.data as EventCollection;

        postsTotal.value = data.total;

        if (data && data.events) {
            events = data.events;
        }
    } catch (error) {
        pageStatus.value = error.status;
    } finally {
        pageLoading.value = false;
    }
};

const handleFilter = async () => {
    if (
        filterKeywords.value != oldFilterValues.keywords ||
        filterLocation.value != oldFilterValues.location ||
        filterDateRange.value != oldFilterValues.dateRange
    ) {
        oldFilterValues.keywords = filterKeywords.value;
        oldFilterValues.location = filterLocation.value;
        oldFilterValues.dateRange = filterDateRange.value;

        postsPage.value = 1;
        handleLoad();
    }
};

watch(
    () => postsPage.value,
    () => {
        handleLoad();
    },
);

filterKeywords.value = getRouterParam(useRoute(), "keywords");
filterLocation.value = getRouterParam(useRoute(), "location");
filterDateRange.value = getRouterParam(useRoute(), "date-range");
handleLoad();
</script>

<style lang="scss">
.page-workshops {
    .events {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        width: 100%;
    }
}

@media (min-width: 768px) {
    .page-workshops {
        .events {
            grid-template-columns: 1fr 1fr;
        }
    }
}

@media (min-width: 1024px) {
    .page-workshops {
        .events {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }
}
</style>
