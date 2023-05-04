<template>
    <router-link
        class="event-card"
        :to="{ name: 'event', params: { id: props.event.id } }">
        <div
            class="thumbnail"
            :style="{
                backgroundImage: `url('${mediaGetVariantUrl(
                    props.event.hero,
                    'medium'
                )}')`,
            }">
            <div :class="['banner', computedBanner(props.event)['type']]">
                {{ computedBanner(props.event)["banner"] }}
            </div>
            <div class="date">
                <div class="day">
                    {{ formatDateDay(props.event.start_at) }}
                </div>
                <div class="month">
                    {{ formatDateMonth(props.event.start_at) }}
                </div>
            </div>
        </div>
        <div class="content">
            <h3 class="title">{{ props.event.title }}</h3>
            <SMRow class="date" no-responsive>
                <ion-icon name="calendar-outline" class="icon" />
                <div class="text">{{ computedDate(props.event) }}</div>
            </SMRow>
            <SMRow class="location" no-responsive>
                <ion-icon name="location-outline" class="icon" />
                <div class="text">
                    {{ computedLocation(props.event) }}
                </div>
            </SMRow>
            <SMRow class="ages" no-responsive>
                <ion-icon name="body-outline" class="icon" />
                <div class="text">
                    {{ computedAges(props.event.ages) }}
                </div>
            </SMRow>
            <SMRow class="price" no-responsive>
                <div class="icon">$</div>
                <div class="text">
                    {{ computedPrice(props.event.price) }}
                </div>
            </SMRow>
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
            str = new SMDate(event.start_at, {
                format: "yMd",
                utc: true,
            }).format("dd/MM/yyyy");
            if (event.end_at.length > 0) {
                str =
                    str +
                    " - " +
                    new SMDate(event.end_at, {
                        format: "yMd",
                        utc: true,
                    }).format("dd/MM/yyyy");
            }
        } else {
            str = new SMDate(event.start_at, {
                format: "yMd",
                utc: true,
            }).format("dd/MM/yyyy @ h:mm aa");
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

type EventBanner = {
    banner: string;
    type: string;
};

const computedBanner = (event: Event): EventBanner => {
    const parsedEndAt = new SMDate(event.end_at, {
        format: "yyyy-MM-dd HH:mm:ss",
        utc: true,
    });

    if (
        (parsedEndAt.isBefore(new SMDate("now")) &&
            (event.status == "open" || event.status == "soon")) ||
        event.status == "closed"
    ) {
        return {
            banner: "closed",
            type: "expired",
        };
    } else if (event.status == "open") {
        return {
            banner: "open",
            type: "success",
        };
    } else if (event.status == "cancelled") {
        return {
            banner: "cancelled",
            type: "danger",
        };
    }

    return {
        banner: "Open Soon",
        type: "warning",
    };
};
</script>

<style lang="scss">
a.event-card {
    background-color: var(--base-color-light);
    box-shadow: 0 5px 10px -3px rgba(0, 0, 0, 0.25);
    border-radius: 8px;
    text-decoration: none;
    color: var(--base-color-text);
    position: relative;
    overflow: hidden;

    &:visited {
        color: var(--card-color-text);
    }

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
</style>
