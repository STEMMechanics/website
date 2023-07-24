<template>
    <SMLoading class="pt-24 pb-48" v-if="pageLoading" />
    <SMPageStatus
        v-else-if="!pageLoading && pageStatus != 200"
        :status="pageStatus" />
    <div v-else>
        <div
            class="max-w-4xl mx-auto h-96 text-center mb-8 relative rounded-4 overflow-hidden">
            <div
                class="blur bg-cover bg-center absolute top-0 left-0 w-full h-full -z-1 opacity-50"
                :style="{
                    backgroundImage: `url('${mediaGetVariantUrl(
                        event.hero,
                        'large',
                    )}')`,
                }"></div>
            <img
                :src="mediaGetVariantUrl(event.hero, 'large')"
                class="h-full" />
        </div>
        <div>
            <div
                class="max-w-4xl mx-auto px-4 flex flex-col-reverse sm:flex-row">
                <div class="sm:pr-8 mt-4 sm:mt-0">
                    <h1 class="pb-6">{{ event.title }}</h1>
                    <SMHTML class="mb-8" :html="event.content" />
                    <SMAttachments :model-value="event.attachments" />
                </div>
                <div class="sm:min-w-68">
                    <div
                        v-if="
                            event.status == 'closed' ||
                            (event.status == 'open' && expired)
                        "
                        class="text-xs px-4 py-2 b-1 border-red-400 bg-red-100 text-red-900 text-center rounded">
                        Registration for this event has closed.
                    </div>
                    <div
                        v-if="event.status == 'soon'"
                        class="text-xs px-4 py-2 b-1 border-yellow-400 bg-yellow-100 text-yellow-900 text-center rounded">
                        Registration for this event will open soon.
                    </div>
                    <div
                        v-if="event.status == 'cancelled'"
                        class="text-xs px-4 py-2 b-1 border-red-400 bg-red-100 text-red-900 text-center rounded">
                        This event has been cancelled.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'none'
                        "
                        class="text-xs px-4 py-2 b-1 border-yellow-400 bg-yellow-100 text-yellow-900 text-center rounded">
                        Registration not required for this event.<br />Arrive
                        early to avoid disappointment as seating maybe limited.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'link'
                        "
                        class="workshop-registration workshop-registration-url">
                        <a
                            role="button"
                            :href="registerUrl"
                            class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-green-600 hover:bg-green-500 text-white block text-center"
                            >Register for Event</a
                        >
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'message'
                        "
                        class="text-xs px-4 py-2 b-1 border-yellow-400 bg-yellow-100 text-yellow-900 text-center rounded">
                        {{ event.registration_data }}
                    </div>
                    <router-link
                        v-if="userHasPermission('admin/events') && event.id"
                        role="button"
                        :to="{
                            name: 'dashboard-event-edit',
                            params: { id: event.id },
                        }"
                        class="font-medium mt-4 px-6 py-1.5 rounded-md hover:shadow-md transition text-sm border-1 bg-white border-sky-6 text-sky-600 block text-center"
                        >Edit Event</router-link
                    >
                    <div class="text-gray-6">
                        <h3 class="flex flex-items-center pb-2 pt-6">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6 pr-1"
                                viewBox="0 -960 960 960">
                                <path
                                    d="M180-80q-24 0-42-18t-18-42v-620q0-24 18-42t42-18h65v-60h65v60h340v-60h65v60h65q24 0 42 18t18 42v620q0 24-18 42t-42 18H180Zm0-60h600v-430H180v430Zm0-490h600v-130H180v130Zm0 0v-130 130Zm300 230q-17 0-28.5-11.5T440-440q0-17 11.5-28.5T480-480q17 0 28.5 11.5T520-440q0 17-11.5 28.5T480-400Zm-160 0q-17 0-28.5-11.5T280-440q0-17 11.5-28.5T320-480q17 0 28.5 11.5T360-440q0 17-11.5 28.5T320-400Zm320 0q-17 0-28.5-11.5T600-440q0-17 11.5-28.5T640-480q17 0 28.5 11.5T680-440q0 17-11.5 28.5T640-400ZM480-240q-17 0-28.5-11.5T440-280q0-17 11.5-28.5T480-320q17 0 28.5 11.5T520-280q0 17-11.5 28.5T480-240Zm-160 0q-17 0-28.5-11.5T280-280q0-17 11.5-28.5T320-320q17 0 28.5 11.5T360-280q0 17-11.5 28.5T320-240Zm320 0q-17 0-28.5-11.5T600-280q0-17 11.5-28.5T640-320q17 0 28.5 11.5T680-280q0 17-11.5 28.5T640-240Z"
                                    fill="currentColor" />
                            </svg>
                            Date / Time
                        </h3>
                        <p
                            v-for="(line, index) in workshopDate"
                            :key="index"
                            class="pl-6 text-sm mt-0">
                            {{ line }}
                        </p>
                    </div>
                    <div class="text-gray-6">
                        <h3 class="flex flex-items-center pb-2 pt-6">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 pr-2"
                                viewBox="0 -960 960 960">
                                <path
                                    d="M480.089-490Q509-490 529.5-510.589q20.5-20.588 20.5-49.5Q550-589 529.411-609.5q-20.588-20.5-49.5-20.5Q451-630 430.5-609.411q-20.5 20.588-20.5 49.5Q410-531 430.589-510.5q20.588 20.5 49.5 20.5ZM480-159q133-121 196.5-219.5T740-552q0-117.79-75.292-192.895Q589.417-820 480-820t-184.708 75.105Q220-669.79 220-552q0 75 65 173.5T480-159Zm0 79Q319-217 239.5-334.5T160-552q0-150 96.5-239T480-880q127 0 223.5 89T800-552q0 100-79.5 217.5T480-80Zm0-472Z"
                                    fill="currentColor" />
                            </svg>
                            Location
                        </h3>
                        <p class="pl-6 text-sm mt-0">
                            <template v-if="event.location == 'online'"
                                >Online event</template
                            >
                            <template
                                v-else-if="event.location_url.length == 0"
                                >{{ event.address }}</template
                            >
                            <template v-else
                                ><a
                                    :href="event.location_url"
                                    no-follow
                                    target="_blank"
                                    >{{ event.address }}</a
                                ></template
                            >
                        </p>
                    </div>
                    <div v-if="event.ages" class="text-gray-6">
                        <h3 class="flex flex-items-center pb-2 pt-6">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 pr-2"
                                viewBox="0 -960 960 960">
                                <path
                                    d="M626-533q22.5 0 38.25-15.75T680-587q0-22.5-15.75-38.25T626-641q-22.5 0-38.25 15.75T572-587q0 22.5 15.75 38.25T626-533Zm-292 0q22.5 0 38.25-15.75T388-587q0-22.5-15.75-38.25T334-641q-22.5 0-38.25 15.75T280-587q0 22.5 15.75 38.25T334-533Zm146 272q66 0 121.5-35.5T682-393H278q26 61 81 96.5T480-261Zm0 181q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-400Zm0 340q142.375 0 241.188-98.812Q820-337.625 820-480t-98.812-241.188Q622.375-820 480-820t-241.188 98.812Q140-622.375 140-480t98.812 241.188Q337.625-140 480-140Z"
                                    fill="currentColor" />
                            </svg>
                            {{ computedAges }}
                        </h3>
                        <p
                            class="text-sm border-l-4 pl-2 ml-2 border-yellow-400">
                            {{ computedAgeNotice }}
                        </p>
                    </div>
                    <div v-if="event.price" class="text-gray-6">
                        <h3 class="flex flex-items-center pb-2 pt-6">
                            <div class="w-6 text-center font-normal">$</div>
                            {{ computedPrice }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, Ref, ref } from "vue";
import { useRoute } from "vue-router";
import SMAttachments from "../components/SMAttachments.vue";
import { api } from "../helpers/api";
import { Event, EventResponse } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { stringToNumber } from "../helpers/string";
import { useApplicationStore } from "../store/ApplicationStore";
import { mediaGetVariantUrl } from "../helpers/media";
import { userHasPermission } from "../helpers/utils";
import SMLoading from "../components/SMLoading.vue";
import SMPageStatus from "../components/SMPageStatus.vue";
import SMHTML from "../components/SMHTML.vue";

const applicationStore = useApplicationStore();

/**
 * Event data
 */
const event: Ref<Event | null> = ref(null);

const route = useRoute();
const pageLoading = ref(true);

/**
 * Page error.
 */
let pageStatus = ref(200);

const workshopDate = computed(() => {
    let str: string[] = [];

    if (Object.keys(event.value).length > 0) {
        if (
            event.value.end_at.length > 0 &&
            event.value.start_at.substring(
                0,
                event.value.start_at.indexOf(" "),
            ) !=
                event.value.end_at.substring(0, event.value.end_at.indexOf(" "))
        ) {
            str = [
                new SMDate(event.value.start_at, { format: "ymd" }).format(
                    "dd/MM/yyyy",
                ),
            ];
            if (event.value.end_at.length > 0) {
                str[0] =
                    str[0] +
                    " - " +
                    new SMDate(event.value.end_at, { format: "ymd" }).format(
                        "dd/MM/yyyy",
                    );
            }
        } else {
            str = [
                new SMDate(event.value.start_at, { format: "ymd" }).format(
                    "EEEE dd MMM yyyy",
                ),
                new SMDate(event.value.start_at, { format: "ymd" }).format(
                    "h:mm aa",
                ) +
                    " - " +
                    new SMDate(event.value.end_at, { format: "ymd" }).format(
                        "h:mm aa",
                    ),
            ];
        }
    }

    return str;
});

/**
 * Return a computed price amount, if a form of 0, return "Free"
 */
const computedPrice = computed(() => {
    if (
        event.value.price.toLowerCase() == "tbc" ||
        event.value.price.toLowerCase() == "tbd"
    ) {
        return event.value.price.toUpperCase();
    }

    const parsedPrice = stringToNumber(event.value.price || "0");
    if (parsedPrice == 0) {
        return "Free";
    }

    return event.value.price;
});

const registerUrl = computed(() => {
    let href = "";

    if (event.value?.registration_type == "link") {
        return event.value?.registration_data;
    } else if (event.value?.registration_type == "email") {
        return "mailto:" + event.value?.registration_data;
    }

    return href;
});

const expired = computed(() => {
    return new SMDate(event.value.end_at, {
        format: "ymd",
    }).isBefore();
});

/**
 * Return a human readable Ages string.
 */
const computedAges = computed(() => {
    const trimmed = event.value.ages.trim();
    const regex = /^(\d+)(\s*\+?\s*|\s*-\s*\d+\s*)?$/;

    if (regex.test(trimmed)) {
        return `Ages ${trimmed}`;
    }

    return event.value.ages;
});

/**
 * Display a age notice if required.
 */
const computedAgeNotice = computed(() => {
    const trimmed = event.value.ages.trim();
    const regex = /^(\d+)(\s*\+?\s*|\s*-\s*\d+\s*)?$/;

    if (regex.test(trimmed)) {
        const age = parseInt(trimmed, 10);
        if (age <= 8) {
            return "Parental supervision may be required for children 8 years of age and under.";
        }
    }

    return "";
});

/**
 * Load the page data.
 */
const handleLoad = async () => {
    pageLoading.value = true;

    try {
        let result = await api.get({
            url: "/events/{event}",
            params: {
                event: route.params.id,
            },
        });

        const eventData = result.data as EventResponse;

        if (eventData && eventData.event) {
            event.value = eventData.event;
            event.value.start_at = new SMDate(event.value.start_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");
            event.value.end_at = new SMDate(event.value.end_at, {
                format: "ymd",
                utc: true,
            }).format("yyyy/MM/dd HH:mm:ss");

            applicationStore.setDynamicTitle(event.value.title);
        } else {
            pageStatus.value = 404;
        }
    } catch (error) {
        pageStatus.value = error.status;
    } finally {
        pageLoading.value = false;
    }
};

handleLoad();
</script>
