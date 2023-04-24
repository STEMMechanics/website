<template>
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
        <SMMessage
            v-if="formMessage"
            icon="alert-circle-outline"
            type="error"
            :message="formMessage"
            class="mt-5" />

        <SMLoading v-if="pageLoading" large />
        <SMNoItems v-else-if="events.length == 0" text="No Workshops Found" />
        <div v-else class="events">
            <router-link
                class="event-card"
                v-for="event in events"
                :key="event.id"
                :to="{ name: 'event', params: { id: event.id } }">
                <div
                    class="thumbnail"
                    :style="{
                        backgroundImage: `url('${mediaGetVariantUrl(
                            event.hero,
                            'medium'
                        )}')`,
                    }">
                    <div :class="['banner', event['bannerType']]">
                        {{ event["banner"] }}
                    </div>
                    <div class="date">
                        <div class="day">
                            {{ formatDateDay(event.start_at) }}
                        </div>
                        <div class="month">
                            {{ formatDateMonth(event.start_at) }}
                        </div>
                    </div>
                </div>
                <div class="content">
                    <h3 class="title">{{ event.title }}</h3>
                    <SMRow class="date" no-responsive>
                        <ion-icon name="calendar-outline" class="icon" />
                        <div class="text">{{ computedDate(event) }}</div>
                    </SMRow>
                    <SMRow class="location" no-responsive>
                        <ion-icon name="location-outline" class="icon" />
                        <div class="text">{{ computedLocation(event) }}</div>
                    </SMRow>
                    <SMRow class="ages" no-responsive>
                        <ion-icon name="body-outline" class="icon" />
                        <div class="text">{{ computedAges(event.ages) }}</div>
                    </SMRow>
                    <SMRow class="price" no-responsive>
                        <div class="icon">$</div>
                        <div class="text">{{ computedPrice(event.price) }}</div>
                    </SMRow>
                </div>
            </router-link>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import SMInput from "../components/SMInput.vue";
import SMMessage from "../components/SMMessage.vue";
import SMPagination from "../components/SMPagination.vue";
import SMToolbar from "../components/SMToolbar.vue";
import { api } from "../helpers/api";
import { Event, EventCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { mediaGetVariantUrl } from "../helpers/media";
import SMMastHead from "../components/SMMastHead.vue";
import SMContainer from "../components/SMContainer.vue";
import SMNoItems from "../components/SMNoItems.vue";
import SMLoading from "../components/SMLoading.vue";

const pageLoading = ref(true);
let events: Event[] = reactive([]);
const dateRangeError = ref("");

const formMessage = ref("123");

const filterKeywords = ref("");
const filterLocation = ref("");
const filterDateRange = ref("");

const postsPerPage = 24;
let postsPage = ref(1);
let postsTotal = ref(0);

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
        formMessage.value = "";
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

                item["banner"] = banner;
                item["bannerType"] = bannerType;

                events.push(item);
            });
        }
    } catch (error) {
        if (error.status != 404) {
            formMessage.value =
                error.response?.data?.message ||
                "Could not load any events from the server.";
        }
    } finally {
        pageLoading.value = false;
    }
};

const handleFilter = async () => {
    postsPage.value = 1;
    handleLoad();
};

/**
 * Return a human readable Date string.
 *
 * @param {Event} event The event to convert.
 * @returns The converted string.
 */
const computedDate = (event: Event) => {
    let str = "";

    if (event.start_at.length > 0) {
        if (
            event.end_at.length > 0 &&
            event.start_at.substring(0, event.start_at.indexOf(" ")) !=
                event.end_at.substring(0, event.end_at.indexOf(" "))
        ) {
            str = new SMDate(event.start_at, { format: "yMd" }).format(
                "dd/MM/yyyy"
            );
            if (event.end_at.length > 0) {
                str =
                    str +
                    " - " +
                    new SMDate(event.end_at, { format: "yMd" }).format(
                        "dd/MM/yyyy"
                    );
            }
        } else {
            str = new SMDate(event.start_at, { format: "yMd" }).format(
                "dd/MM/yyyy @ h:mm aa"
            );
        }
    }

    return str;
};

/**
 * Return a the event starting month day number.
 *
 * @param {string} date The date to format.
 * @returns The converted string.
 */
const formatDateDay = (date: string) => {
    return new SMDate(date, { format: "yMd" }).format("dd");
};

/**
 * Return a the event starting month name.
 *
 * @param {string} date The date to format.
 * @returns The converted string.
 */
const formatDateMonth = (date: string) => {
    return new SMDate(date, { format: "yMd" }).format("MMM");
};

/**
 * Return a human readable Location string.
 *
 * @param {Event} event The event to convert.
 * @returns The converted string.
 */
const computedLocation = (event: Event): string => {
    if (event.location == "online") {
        return "Online";
    }

    return event.address;
};

/**
 * Return a human readable Ages string.
 *
 * @param {string} ages The string to convert.
 * @returns The converted string.
 */
const computedAges = (ages: string): string => {
    const trimmed = ages.trim();
    const regex = /^(\d+)(\s*\+?\s*|\s*-\s*\d+\s*)?$/;

    if (trimmed.length === 0) {
        return "All ages";
    }

    if (regex.test(trimmed)) {
        return `Ages ${trimmed}`;
    }

    return ages;
};

/**
 * Return a human readable Price string.
 *
 * @param {string} price The string to convert.
 * @returns The converted string.
 */
const computedPrice = (price: string): string => {
    const trimmed = parseInt(price.trim());
    if (isNaN(trimmed) || trimmed == 0) {
        return "Free";
    }

    return trimmed.toString();
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

        .event-card {
            background-color: var(--base-color-light);
            box-shadow: 0 5px 10px -3px rgba(0, 0, 0, 0.25);
            border-radius: 8px;
            text-decoration: none;
            color: var(--base-color-text);
            position: relative;
            overflow: hidden;

            .thumbnail {
                width: 100%;
                aspect-ratio: 16 / 9;
                background-position: center;
                background-repeat: no-repeat;
                background-size: cover;
                border-radius: 8px 8px 0 0;

                .banner {
                    position: absolute;
                    background-color: var(--banner-green-color);
                    font-size: 70%;
                    font-weight: 700;
                    color: var(--banner-green-color-text);
                    padding: 6px 18px;
                    text-align: center;
                    top: 10px;
                    right: 10px;
                    text-transform: uppercase;

                    &.expired {
                        background-color: var(--banner-purple-color);
                        color: var(--banner-purple-color-text);
                    }

                    &.danger {
                        background-color: var(--banner-red-color);
                        color: var(--banner-red-color-text);
                    }

                    &.warning {
                        background-color: var(--banner-yellow-color);
                        color: var(--banner-yellow-color-text);
                    }
                }

                .date {
                    position: absolute;
                    top: 10px;
                    left: 10px;
                    background-color: var(--base-color);
                    box-shadow: var(--base-shadow);
                    padding: 8px 12px;
                    text-align: center;
                    border-radius: 2px;

                    .day {
                        font-weight: 700;
                        padding: 1px;
                    }

                    .month {
                        font-size: 65%;
                        text-transform: uppercase;
                    }
                }
            }

            .content {
                padding: 16px;
            }

            .title {
                margin: 0 0 16px 0;
                font-size: 100%;
                word-break: break-all;
            }

            .row {
                display: flex;
                margin-bottom: 8px;
                font-size: 80%;

                .icon {
                    width: 20px;
                    text-align: center;
                    margin-right: 8px;
                }
            }

            &:hover {
                cursor: pointer;
                filter: none;

                .image {
                    filter: brightness(115%);
                }
            }
        }
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
