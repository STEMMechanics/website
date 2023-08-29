<template>
    <router-link
        rel="prefetch"
        :to="{ name: 'event', params: { id: props.event.id } }"
        class="event-card bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-72">
        <div
            class="h-48 bg-cover bg-center rounded-t-xl relative"
            :style="{
                backgroundImage: `url('${mediaGetVariantUrl(
                    props.event.hero,
                    'medium',
                )}')`,
            }">
            <div
                :class="[
                    'absolute',
                    'top-2',
                    'right-2',
                    'text-xs',
                    'font-bold',
                    'uppercase',
                    'px-4',
                    'py-1',
                    computedBanner(props.event)['bg-class'],
                    computedBanner(props.event)['font-class'],
                ]">
                {{ computedBanner(props.event)["banner"] }}
            </div>
            <div
                class="flex flex-col items-center justify-center bg-white border-1 border-rounded absolute top-2 left-2 w-12 h-12 text-gray-6">
                <div class="font-bold line-height-none">
                    {{ formatDateDay(props.event.start_at) }}
                </div>
                <div class="text-xs uppercase line-height-none">
                    {{ formatDateMonth(props.event.start_at) }}
                </div>
            </div>
        </div>
        <div class="p-4">
            <h3 class="mb-3 font-500">{{ props.event.title }}</h3>
            <div class="flex items-center mb-2 text-gray-5">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 pr-2"
                    viewBox="0 -960 960 960">
                    <path
                        d="M180-80q-24 0-42-18t-18-42v-620q0-24 18-42t42-18h65v-60h65v60h340v-60h65v60h65q24 0 42 18t18 42v620q0 24-18 42t-42 18H180Zm0-60h600v-430H180v430Zm0-490h600v-130H180v130Zm0 0v-130 130Zm300 230q-17 0-28.5-11.5T440-440q0-17 11.5-28.5T480-480q17 0 28.5 11.5T520-440q0 17-11.5 28.5T480-400Zm-160 0q-17 0-28.5-11.5T280-440q0-17 11.5-28.5T320-480q17 0 28.5 11.5T360-440q0 17-11.5 28.5T320-400Zm320 0q-17 0-28.5-11.5T600-440q0-17 11.5-28.5T640-480q17 0 28.5 11.5T680-440q0 17-11.5 28.5T640-400ZM480-240q-17 0-28.5-11.5T440-280q0-17 11.5-28.5T480-320q17 0 28.5 11.5T520-280q0 17-11.5 28.5T480-240Zm-160 0q-17 0-28.5-11.5T280-280q0-17 11.5-28.5T320-320q17 0 28.5 11.5T360-280q0 17-11.5 28.5T320-240Zm320 0q-17 0-28.5-11.5T600-280q0-17 11.5-28.5T640-320q17 0 28.5 11.5T680-280q0 17-11.5 28.5T640-240Z"
                        fill="currentColor" />
                </svg>
                <span class="text-sm">{{ computedDate(props.event) }}</span>
            </div>
            <div class="flex items-center mb-2 text-gray-5">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 pr-2"
                    viewBox="0 -960 960 960">
                    <path
                        d="M480.089-490Q509-490 529.5-510.589q20.5-20.588 20.5-49.5Q550-589 529.411-609.5q-20.588-20.5-49.5-20.5Q451-630 430.5-609.411q-20.5 20.588-20.5 49.5Q410-531 430.589-510.5q20.588 20.5 49.5 20.5ZM480-159q133-121 196.5-219.5T740-552q0-117.79-75.292-192.895Q589.417-820 480-820t-184.708 75.105Q220-669.79 220-552q0 75 65 173.5T480-159Zm0 79Q319-217 239.5-334.5T160-552q0-150 96.5-239T480-880q127 0 223.5 89T800-552q0 100-79.5 217.5T480-80Zm0-472Z"
                        fill="currentColor" />
                </svg>
                <span class="text-sm">
                    {{ computedLocation(props.event) }}
                </span>
            </div>
            <div class="flex items-center mb-2 text-gray-5">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 pr-2"
                    viewBox="0 -960 960 960">
                    <path
                        d="M626-533q22.5 0 38.25-15.75T680-587q0-22.5-15.75-38.25T626-641q-22.5 0-38.25 15.75T572-587q0 22.5 15.75 38.25T626-533Zm-292 0q22.5 0 38.25-15.75T388-587q0-22.5-15.75-38.25T334-641q-22.5 0-38.25 15.75T280-587q0 22.5 15.75 38.25T334-533Zm146 272q66 0 121.5-35.5T682-393H278q26 61 81 96.5T480-261Zm0 181q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-400Zm0 340q142.375 0 241.188-98.812Q820-337.625 820-480t-98.812-241.188Q622.375-820 480-820t-241.188 98.812Q140-622.375 140-480t98.812 241.188Q337.625-140 480-140Z"
                        fill="currentColor" />
                </svg>
                <span class="text-sm">
                    {{ computedAges(props.event.ages) }}
                </span>
            </div>
            <div class="flex items-center text-gray-5">
                <span class="block text-center w-4 mr-2">$</span>
                <span class="text-sm">
                    {{ computedPrice(props.event.price) }}
                </span>
            </div>
        </div>
    </router-link>
</template>

<script setup lang="ts">
import { Event } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { mediaGetVariantUrl } from "../helpers/media";

const props = defineProps({
    event: {
        type: Object as () => Event,
        required: true,
    },
});

/**
 * Return a human readable Date string.
 * @param {Event} event The event to convert.
 * @returns The converted string.
 */
const computedDate = (event: Event) => {
    let str = "";

    const start_at =
        event.start_at.length > 0
            ? new SMDate(event.start_at, {
                  format: "yMd",
                  utc: true,
              }).format("dd/MM/yyyy  @ h:mm aa")
            : "";

    const end_at =
        event.end_at.length > 0
            ? new SMDate(event.end_at, {
                  format: "yMd",
                  utc: true,
              }).format("dd/MM/yyyy")
            : "";

    if (start_at.length > 0) {
        if (
            end_at.length > 0 &&
            start_at.substring(0, start_at.indexOf(" ")) != end_at
        ) {
            str = start_at.substring(0, start_at.indexOf(" ")) + " - " + end_at;
        } else {
            str = start_at;
        }
    }

    return str;
};

/**
 * Return a the event starting month day number.
 * @param {string} date The date to format.
 * @returns The converted string.
 */
const formatDateDay = (date: string) => {
    return new SMDate(date, { format: "yMd", utc: true }).format("dd");
};

/**
 * Return a the event starting month name.
 * @param {string} date The date to format.
 * @returns The converted string.
 */
const formatDateMonth = (date: string) => {
    return new SMDate(date, { format: "yMd", utc: true }).format("MMM");
};

/**
 * Return a human readable Location string.
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
 * @param {string} price The string to convert.
 * @returns The converted string.
 */
const computedPrice = (price: string): string => {
    if (price.toLowerCase() === "tbd" || price.toLowerCase() === "tbc") {
        return price.toUpperCase();
    }

    const trimmed = parseInt(price.trim());
    if (isNaN(trimmed) || trimmed == 0) {
        return "Free";
    }

    return trimmed.toString();
};

type EventBanner = {
    banner: string;
    "bg-class": string;
    "font-class": string;
};

const computedBanner = (event: Event): EventBanner => {
    const parsedEndAt = new SMDate(event.end_at, {
        format: "yyyy-MM-dd HH:mm:ss",
        utc: true,
    });

    if (
        (parsedEndAt.isBefore(new SMDate("now")) &&
            (event.status == "open" ||
                event.status == "soon" ||
                event.status == "full")) ||
        event.status == "closed"
    ) {
        return {
            banner: "closed",
            "bg-class": "bg-purple-800",
            "font-class": "text-white",
        };
    } else if (event.status == "full") {
        return {
            banner: "full",
            "bg-class": "bg-purple-800",
            "font-class": "text-white",
        };
    } else if (event.status == "open") {
        return {
            banner: "open",
            "bg-class": "bg-green-700",
            "font-class": "text-white",
        };
    } else if (event.status == "cancelled") {
        return {
            banner: "cancelled",
            "bg-class": "bg-red-700",
            "font-class": "text-white",
        };
    } else if (event.status == "draft") {
        return {
            banner: "draft",
            "bg-class": "bg-purple-800",
            "font-class": "text-white",
        };
    }

    return {
        banner: "Open Soon",
        "bg-class": "bg-yellow-400",
        "font-class": "text-black",
    };
};
</script>

<style lang="scss">
.event-card {
    color: inherit !important;
}
</style>
