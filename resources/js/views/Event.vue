<template>
    <SMPage :page-error="pageError" :loading="pageLoading">
        <div
            class="workshop-image"
            :style="{
                backgroundImage: `url('${mediaGetVariantUrl(event.hero)}')`,
            }"></div>
        <SMContainer>
            <SMContainer class="workshop-page">
                <div class="workshop-body">
                    <h2 class="workshop-title">{{ event.title }}</h2>
                    <SMHTML :html="event.content" class="workshop-content" />
                    <SMAttachments :attachments="event.attachments || []" />
                </div>
                <div class="workshop-info">
                    <div
                        v-if="
                            event.status == 'closed' ||
                            (event.status == 'open' && expired)
                        "
                        class="workshop-registration workshop-registration-closed">
                        Registration for this event has closed.
                    </div>
                    <div
                        v-if="event.status == 'soon'"
                        class="workshop-registration workshop-registration-soon">
                        Registration for this event will open soon.
                    </div>
                    <div
                        v-if="event.status == 'cancelled'"
                        class="workshop-registration workshop-registration-cancelled">
                        This event has been cancelled.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'none'
                        "
                        class="workshop-registration workshop-registration-none">
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
                        <SMButton
                            :to="registerUrl"
                            :block="true"
                            label="Register for Event"></SMButton>
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'message'
                        "
                        class="workshop-registration workshop-registration-message">
                        {{ event.registration_data }}
                    </div>
                    <div
                        v-if="userHasPermission('admin/events') && event.id"
                        class="workshop-edit">
                        <SMButton
                            block
                            size="medium"
                            type="primary"
                            :to="{
                                name: 'dashboard-event-edit',
                                params: { id: event.id },
                            }"
                            label="Edit Event" />
                    </div>
                    <div class="workshop-date">
                        <h4>
                            <ion-icon
                                class="icon"
                                name="calendar-outline" />Date / Time
                        </h4>
                        <p
                            v-for="(line, index) in workshopDate"
                            :key="index"
                            class="workshop-date-string">
                            {{ line }}
                        </p>
                    </div>
                    <div class="workshop-location">
                        <h4>
                            <ion-icon
                                class="icon"
                                name="location-outline" />Location
                        </h4>
                        <p>
                            {{
                                event.location == "online"
                                    ? "Online event"
                                    : event.address
                            }}
                        </p>
                    </div>
                    <div v-if="event.ages" class="workshop-ages">
                        <h4>
                            <ion-icon class="icon" name="body-outline" />{{
                                computedAges
                            }}
                        </h4>
                        <p>{{ computedAgeNotice }}</p>
                    </div>
                    <div v-if="event.price" class="workshop-price">
                        <h4><span class="icon">$</span>{{ computedPrice }}</h4>
                    </div>
                </div>
            </SMContainer>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { computed, Ref, ref } from "vue";
import { useRoute } from "vue-router";
import SMButton from "../components/SMButton.vue";
import SMHTML from "../components/SMHTML.vue";
import SMAttachments from "../components/SMAttachments.vue";
import { api } from "../helpers/api";
import { Event, EventResponse } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { stringToNumber } from "../helpers/string";
import { useApplicationStore } from "../store/ApplicationStore";
import { mediaGetVariantUrl } from "../helpers/media";
import SMPage from "../components/SMPage.vue";
import { userHasPermission } from "../helpers/utils";

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
let pageError = ref(200);

const workshopDate = computed(() => {
    let str: string[] = [];

    if (Object.keys(event.value).length > 0) {
        if (
            event.value.end_at.length > 0 &&
            event.value.start_at.substring(
                0,
                event.value.start_at.indexOf(" ")
            ) !=
                event.value.end_at.substring(0, event.value.end_at.indexOf(" "))
        ) {
            str = [
                new SMDate(event.value.start_at, { format: "ymd" }).format(
                    "dd/MM/yyyy"
                ),
            ];
            if (event.value.end_at.length > 0) {
                str[0] =
                    str[0] +
                    " - " +
                    new SMDate(event.value.end_at, { format: "ymd" }).format(
                        "dd/MM/yyyy"
                    );
            }
        } else {
            str = [
                new SMDate(event.value.start_at, { format: "ymd" }).format(
                    "EEEE dd MMM yyyy"
                ),
                new SMDate(event.value.start_at, { format: "ymd" }).format(
                    "h:mm aa"
                ) +
                    " - " +
                    new SMDate(event.value.end_at, { format: "ymd" }).format(
                        "h:mm aa"
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
            pageError.value = 404;
        }
    } catch (error) {
        pageError.value = error.status;
    } finally {
        pageLoading.value = false;
    }
};

handleLoad();
</script>

<style lang="scss">
.workshop-image {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 256px;
    height: 20vw;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    background-color: var(--base-color);
    transition: background-image 0.2s;

    .workshop-image-loader {
        font-size: 350%;
        color: var(--base-color);
    }
}

.page-event .workshop-page {
    display: flex;
    flex-direction: row;

    .workshop-body {
        flex: 1;
        text-align: left;
    }

    .workshop-title {
        margin-bottom: 32px;
    }

    .workshop-info {
        width: 288px;
        margin-left: 32px;

        h4 {
            display: flex;
            align-items: center;

            .icon {
                display: inline-block;
                font-size: 20px;
                margin-right: 8px;
                text-align: center;
            }
        }

        p {
            margin: 0;
            padding-left: 28px;
            font-size: 90%;
        }

        .workshop-registration {
            margin-top: 32px;
        }

        .workshop-registration-none,
        .workshop-registration-soon,
        .workshop-registration-message {
            border: 1px solid var(--warning-color-light);
            background-color: var(--warning-color-lighter);
            color: var(--warning-color-dark);
            text-align: center;
            font-size: 80%;
            padding: 8px;
        }

        .workshop-registration-closed,
        .workshop-registration-cancelled {
            border: 1px solid var(--danger-color-light);
            background-color: var(--danger-color-lighter);
            color: var(--danger-color-dark);
            text-align: center;
            font-size: 80%;
            padding: 8px;
        }

        .workshop-date,
        .workshop-location,
        .workshop-price,
        .workshop-ages {
            padding: 0 16px;
        }

        .workshop-ages p {
            margin-top: 8px;
            margin-left: 16px;
            padding: 0 0 0 8px;
            font-size: 80%;
            border-left: 4px solid var(--warning-color-dark);
        }
    }
}

@media screen and (max-width: 768px) {
    .workshop-page {
        flex-direction: column;

        .workshop-body {
            text-align: center;
        }

        .workshop-info {
            width: 100%;
            margin-left: 0;

            h4 {
                justify-content: center;
            }

            p {
                padding-left: 0;
                text-align: center;
            }

            .workshop-ages p {
                margin-left: 0;
                border-left: 0;
            }
        }
    }
}
</style>
