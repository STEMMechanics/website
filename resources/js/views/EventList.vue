<template>
    <SMPage class="sm-page-workshop-list">
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
                    v-for="item in events"
                    :key="item.event.id"
                    :to="{ name: 'event-view', params: { id: item.event.id } }"
                    :title="item.event.title"
                    :image="item.event.hero"
                    :show-time="true"
                    :date="item.event.start_at"
                    :end-date="item.event.end_at"
                    :date-in-image="true"
                    :price="item.event.price"
                    :location="
                        item.event.location == 'online'
                            ? 'Online Event'
                            : item.event.address
                    "
                    :banner="item.banner"
                    :banner-type="item.bannerType"
                    :ages="computedAges(item.event)"></SMPanel>
            </SMPanelList>
            <SMPagination
                v-model="postsPage"
                :total="postsTotal"
                :per-page="postsPerPage" />
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import SMInput from "../components/SMInput.vue";
import SMMessage from "../components/SMMessage.vue";
import SMPagination from "../components/SMPagination.vue";
import SMPanel from "../components/SMPanel.vue";
import SMPanelList from "../components/SMPanelList.vue";
import SMToolbar from "../components/SMToolbar.vue";
import { api } from "../helpers/api";
import { Event, EventCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";

interface EventData {
    event: Event;
    banner: string;
    bannerType: string;
}

const loading = ref(true);
let events: EventData[] = reactive([]);
const dateRangeError = ref("");

const formMessage = ref("");

const filterKeywords = ref("");
const filterLocation = ref("");
const filterDateRange = ref("");

const postsPerPage = 9;
let postsPage = ref(1);
let postsTotal = ref(0);

/**
 * Load page data.
 */
const handleLoad = async () => {
    try {
        let query = {};

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

        let result = await api.get({
            url: "/events",
            params: query,
        });

        const data = result.data as EventCollection;

        postsTotal.value = data.total;

        if (data && data.events) {
            events = [];

            data.events.forEach((item) => {
                let banner = "";
                let bannerType = "";

                const parsedStartAt = new SMDate(item.start_at, {
                    format: "yyyy-MM-dd HH:mm:ss",
                    utc: true,
                });

                const parsedEndAt = new SMDate(item.end_at, {
                    format: "yyyy-MM-dd HH:mm:ss",
                    utc: true,
                });

                item.start_at = parsedStartAt.format("yyyy-MM-dd HH:mm:ss");

                item.end_at = parsedEndAt.format("yyyy-MM-dd HH:mm:ss");

                if (
                    parsedEndAt.isBefore(new SMDate("now")) ||
                    item.status == "closed"
                ) {
                    banner = "closed";
                    bannerType = "expired";
                } else if (item.status == "open") {
                    banner = "open";
                    bannerType = "success";
                } else if (item.status == "cancelled") {
                    banner = "cancelled";
                    bannerType = "danger";
                } else if (item.status == "soon") {
                    banner = "Open Soon";
                    bannerType = "warning";
                }

                events.push({
                    event: item,
                    banner: banner,
                    bannerType: bannerType,
                });
            });
        }
    } catch (error) {
        if (error.status != 404) {
            formMessage.value =
                error.response?.data?.message ||
                "Could not load any events from the server.";
        }
    } finally {
        loading.value = false;
    }
};

const handleFilter = async () => {
    handleLoad();
};

/**
 * Return a human readable Ages string.
 *
 * @param item
 */
const computedAges = (item: Event): string => {
    const trimmed = item.ages.trim();
    const regex = /^(\d+)(\s*\+?\s*|\s*-\s*\d+\s*)?$/;

    if (regex.test(trimmed)) {
        return `Ages ${trimmed}`;
    }

    return item.ages;
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
.sm-page-workshop-list {
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
    .sm-page-workshop-list .toolbar {
        flex-direction: column;

        & > * {
            padding-left: 0;
            padding-right: 0;
        }
    }
}
</style>
