<template>
    <SMMastHead title="Workshops" />
    <SMContainer>
        <SMToolbar>
            <SMInput
                v-model="filterKeywords"
                label="Keywords"
                :show-clear="true"
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

        <div class="events-list">
            <router-link
                class="event"
                v-for="event in events"
                :key="event.id"
                :to="{ name: 'event', params: { id: event.id } }">
                <div
                    class="image"
                    :style="{
                        backgroundImage: `url('${event.hero.url}')`,
                    }"></div>
                <div class="content">
                    <h3 class="title">{{ event.title }}</h3>
                    <div class="row date">
                        <ion-icon name="calendar-outline" class="icon" />
                        <div class="text">{{ computedDate(event) }}</div>
                    </div>
                    <div class="row location">
                        <ion-icon name="location-outline" class="icon" />
                        <div class="text">{{ computedLocation(event) }}</div>
                    </div>
                    <div class="row ages">
                        <ion-icon name="body-outline" class="icon" />
                        <div class="text">{{ computedAges(event.ages) }}</div>
                    </div>
                    <div class="row price">
                        <div class="icon">$</div>
                        <div class="text">{{ computedPrice(event.price) }}</div>
                    </div>
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
import SMMastHead from "../components/SMMastHead.vue";
import SMContainer from "../components/SMContainer.vue";

interface EventData {
    event: Event;
    banner: string;
    bannerType: string;
}

const loading = ref(true);
let events: Event[] = reactive([]);
const dateRangeError = ref("");

const formMessage = ref("");

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

        if (filterKeywords.value && filterKeywords.value.length > 0) {
            let value = filterKeywords.value.replace(/"/g, '\\"');

            query["filter"] = `(title:"${value}",OR,content:"${value}")`;
        }
        if (filterLocation.value && filterLocation.value.length > 0) {
            query["location"] = filterLocation.value;
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
        loading.value = false;
    }
};

const handleFilter = async () => {
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
    if (trimmed == 0) {
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
    .events-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        width: 100%;

        .event {
            background-color: var(--base-color-light);
            box-shadow: 0 5px 10px -3px rgba(0, 0, 0, 0.25);
            border-radius: 8px;
            text-decoration: none;
            color: var(--base-color-text);

            .image {
                width: 100%;
                aspect-ratio: 16 / 9;
                background-position: center;
                background-repeat: no-repeat;
                background-size: cover;
                border-radius: 8px 8px 0 0;
            }

            .content {
                padding: 16px;
            }

            .title {
                margin: 0 0 16px 0;
                font-size: 100%;
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
        .events-list {
            grid-template-columns: 1fr 1fr;
        }
    }
}

@media (min-width: 1024px) {
    .page-workshops {
        .events-list {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }
}

// .sm-page-workshop-list {
//     background-color: #f8f8f8;

//     .toolbar {
//         display: flex;
//         flex-direction: row;
//         flex: 1;

//         & > * {
//             padding-left: map-get($spacer, 1);
//             padding-right: map-get($spacer, 1);

//             &:first-child {
//                 padding-left: 0;
//             }

//             &:last-child {
//                 padding-right: 0;
//             }
//         }
//     }
// }

// @media screen and (max-width: 768px) {
//     .sm-page-workshop-list .toolbar {
//         flex-direction: column;

//         & > * {
//             padding-left: 0;
//             padding-right: 0;
//         }
//     }
// }
</style>
