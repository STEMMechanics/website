<template>
    <SMPage :page-error="pageError" :loading="pageLoading">
        <div
            class="sm-workshop-image"
            :style="{
                backgroundImage: `url('${mediaGetVariantUrl(event.hero)}')`,
            }"></div>
        <SMContainer>
            <SMMessage
                v-if="formMessage"
                icon="alert-circle-outline"
                type="error"
                :message="formMessage"
                class="mt-5" />
            <SMContainer class="sm-workshop-page">
                <div class="sm-workshop-body">
                    <h2 class="sm-workshop-title">{{ event.title }}</h2>
                    <SMHTML :html="event.content" class="sm-workshop-content" />
                    <SMAttachments :attachments="event.attachments || []" />
                </div>
                <div class="sm-workshop-info">
                    <div
                        v-if="
                            event.status == 'closed' ||
                            (event.status == 'open' && expired)
                        "
                        class="sm-workshop-registration sm-workshop-registration-closed">
                        Registration for this event has closed.
                    </div>
                    <div
                        v-if="event.status == 'soon'"
                        class="sm-workshop-registration sm-workshop-registration-soon">
                        Registration for this event will open soon.
                    </div>
                    <div
                        v-if="event.status == 'cancelled'"
                        class="sm-workshop-registration sm-workshop-registration-cancelled">
                        This event has been cancelled.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'none'
                        "
                        class="sm-workshop-registration sm-workshop-registration-none">
                        Registration not required for this event.<br />Arrive
                        early to avoid disappointment as seating maybe limited.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            expired == false &&
                            event.registration_type == 'link'
                        "
                        class="sm-workshop-registration sm-workshop-registration-url">
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
                        class="sm-workshop-registration sm-workshop-registration-message">
                        {{ event.registration_data }}
                    </div>
                    <div class="sm-workshop-date">
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
                    <div class="sm-workshop-location">
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
                    <div v-if="event.ages" class="sm-workshop-ages">
                        <h4>
                            <ion-icon class="icon" name="body-outline" />{{
                                computedAges
                            }}
                        </h4>
                        <p>{{ computedAgeNotice }}</p>
                    </div>
                    <div v-if="event.price" class="sm-workshop-price">
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
import SMMessage from "../components/SMMessage.vue";
import SMAttachments from "../components/SMAttachments.vue";
import { api } from "../helpers/api";
import { Event, EventResponse } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { stringToNumber } from "../helpers/string";
import { useApplicationStore } from "../store/ApplicationStore";
import { mediaGetVariantUrl } from "../helpers/media";
import SMPage from "../components/SMPage.vue";

const applicationStore = useApplicationStore();

/**
 * Event data
 */
const event: Ref<Event | null> = ref(null);

const route = useRoute();
const pageLoading = ref(true);

/**
 * Page message.
 */
const formMessage = ref("");

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
    formMessage.value = "";

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
        if (error.status == 404) {
            pageError.value = 404;
        } else {
            formMessage.value =
                error.data?.message ||
                "Could not load event information from the server.";
        }
    } finally {
        pageLoading.value = false;
    }
};

handleLoad();
</script>

<style lang="scss">
.sm-workshop-image {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: map-get($spacer, 5) * 4;
    height: 20vw;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    background-color: #eee;
    transition: background-image 0.2s;

    .sm-workshop-image-loader {
        font-size: 5rem;
        color: $secondary-color;
    }
}

.sm-workshop-page {
    display: flex;
    flex-direction: row;

    .sm-workshop-body,
    .sm-workshop-info {
        line-height: 1.5rem;
    }

    .sm-workshop-body {
        flex: 1;
        text-align: left;
    }

    .sm-workshop-title {
        line-height: 1.15em;
        margin-bottom: 32px;
    }

    .sm-workshop-info {
        width: 18rem;
        margin-left: 2rem;

        h4 {
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            height: 1rem;

            .icon {
                display: inline-block;
                width: 1rem;
                margin-right: 0.5rem;
                text-align: center;
            }
        }

        p {
            margin: 0;
            padding-left: 1.5rem;
            font-size: 90%;
        }

        .sm-workshop-registration {
            margin-top: 1.5rem;
            line-height: 1.25rem;
        }

        .sm-workshop-registration-none,
        .sm-workshop-registration-soon,
        .sm-workshop-registration-message {
            border: 1px solid #ffeeba;
            background-color: #fff3cd;
            color: #856404;
            text-align: center;
            font-size: 80%;
            padding: 0.5rem;
        }

        .sm-workshop-registration-closed,
        .sm-workshop-registration-cancelled {
            border: 1px solid #f5c2c7;
            background-color: #f8d7da;
            color: #842029;
            text-align: center;
            font-size: 80%;
            padding: 0.5rem;
        }

        .sm-workshop-date,
        .sm-workshop-location,
        .sm-workshop-price,
        .sm-workshop-ages {
            padding: 0 1rem;
        }

        .sm-workshop-ages p {
            margin-top: 0.5rem;
            margin-left: 1rem;
            padding: 0 0 0 0.5rem;
            font-size: 80%;
            border-left: 4px solid $warning-color;
            line-height: 1.2rem;
        }
    }
}

@media screen and (max-width: 768px) {
    .sm-workshop-page {
        flex-direction: column;

        .sm-workshop-body {
            text-align: center;
        }

        .sm-workshop-info {
            width: 100%;
            margin-left: 0;

            h4 {
                justify-content: center;
            }

            p {
                padding-left: 0;
                text-align: center;
            }

            .sm-workshop-ages p {
                margin-left: 0;
                border-left: 0;
            }
        }
    }
}
</style>
