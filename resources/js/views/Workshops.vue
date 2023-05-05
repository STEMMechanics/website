<template>
    <SMPage :page-error="pageError">
        <SMMastHead title="Workshops" />
        <SMContainer class="flex-grow-1">
            <SMToolbar class="align-items-start">
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
            </SMToolbar>
            <SMPagination
                v-if="postsTotal > postsPerPage"
                v-model="postsPage"
                :total="postsTotal"
                :per-page="postsPerPage" />
            <SMLoading v-if="pageLoading" large />
            <SMNoItems
                v-else-if="events.length == 0"
                text="No Workshops Found" />
            <div v-else class="events">
                <SMEventCard
                    v-for="event in events"
                    :event="event"
                    :key="event.id" />
            </div>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import SMInput from "../components/SMInput.vue";
import SMPagination from "../components/SMPagination.vue";
import SMToolbar from "../components/SMToolbar.vue";
import SMPage from "../components/SMPage.vue";
import { api } from "../helpers/api";
import { Event, EventCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import SMMastHead from "../components/SMMastHead.vue";
import SMContainer from "../components/SMContainer.vue";
import SMNoItems from "../components/SMNoItems.vue";
import SMLoading from "../components/SMLoading.vue";
import SMEventCard from "../components/SMEventCard.vue";

const pageLoading = ref(true);
let events: Event[] = reactive([]);
const dateRangeError = ref("");

const filterKeywords = ref("");
const filterLocation = ref("");
const filterDateRange = ref("");

const postsPerPage = 24;
let postsPage = ref(1);
let postsTotal = ref(0);
const pageError = ref(0);

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

        if (Object.keys(query).length == 0) {
            const now = new Date();
            const startingDate = new Date(now.setDate(now.getDate() - 14));

            query["end_at"] =
                ">" +
                new SMDate(startingDate).format("yyyy/MM/dd HH:mm:ss", {
                    utc: true,
                });
        }

        query["limit"] = postsPerPage;
        query["page"] = postsPage.value;
        query["sort"] = "start_at";

        let result = await api.get({
            url: "/events",
            params: query,
        });

        const data = result.data as EventCollection;

        postsTotal.value = data.total;

        if (data && data.events) {
            events = data.events;

            // data.events.forEach((item) => {
            //     let banner = "";
            //     let bannerType = "";

            //     const parsedStartAt = new SMDate(item.start_at, {
            //         format: "yyyy-MM-dd HH:mm:ss",
            //         utc: true,
            //     });

            //     const parsedEndAt = new SMDate(item.end_at, {
            //         format: "yyyy-MM-dd HH:mm:ss",
            //         utc: true,
            //     });

            //     item.start_at = parsedStartAt.format("yyyy-MM-dd HH:mm:ss");

            //     item.end_at = parsedEndAt.format("yyyy-MM-dd HH:mm:ss");

            //     if (
            //         (parsedEndAt.isBefore(new SMDate("now")) &&
            //             (item.status == "open" || item.status == "soon")) ||
            //         item.status == "closed"
            //     ) {
            //         banner = "closed";
            //         bannerType = "expired";
            //     } else if (item.status == "open") {
            //         banner = "open";
            //         bannerType = "success";
            //     } else if (item.status == "cancelled") {
            //         banner = "cancelled";
            //         bannerType = "danger";
            //     } else if (item.status == "soon") {
            //         banner = "Open Soon";
            //         bannerType = "warning";
            //     }

            //     item["banner"] = banner;
            //     item["bannerType"] = bannerType;

            //     events.push(item);
            // });
        }
    } catch (error) {
        pageError.value = error.status;
    } finally {
        pageLoading.value = false;
    }
};

const handleFilter = async () => {
    postsPage.value = 1;
    handleLoad();
};

watch(
    () => postsPage.value,
    () => {
        handleLoad();
    }
);

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
