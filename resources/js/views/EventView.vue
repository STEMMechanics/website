<template>
    <SMPage
        :full="true"
        :loading="imageUrl.length == 0"
        class="sm-workshop-view"
        :error="pageError">
        <div
            class="sm-workshop-image"
            :style="{ backgroundImage: `url('${imageUrl}')` }"></div>
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
                            event.registration_type != 'none'
                        "
                        class="sm-workshop-registration sm-workshop-registration-url">
                        <SMButton
                            :href="registerUrl"
                            :block="true"
                            label="Register for Event"></SMButton>
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
import { api } from "../helpers/api";
import { Event, EventResponse, MediaResponse } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { imageLoad } from "../helpers/image";
import { stringToNumber } from "../helpers/string";
import { useApplicationStore } from "../store/ApplicationStore";

const applicationStore = useApplicationStore();

/**
 * Event data
 */
const event: Ref<Event | null> = ref(null);

const imageUrl = ref("");

const route = useRoute();

/**
 * Page message.
 */
const formMessage = ref("");

/**
 * Page error.
 */
let pageError = 200;

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
            handleLoadImage();
        } else {
            pageError = 404;
        }
    } catch (error) {
        formMessage.value =
            error.data?.message ||
            "Could not load event information from the server.";
    }
};

/**
 * Load the hero image.
 */
const handleLoadImage = async () => {
    api.get({
        url: "/media/{medium}",
        params: {
            medium: event.value.hero,
        },
    })
        .then((result) => {
            const data = result.data as MediaResponse;

            if (data && data.medium) {
                imageLoad(data.medium.url, (url) => {
                    imageUrl.value = url;
                });
            }
        })
        .catch(() => {
            /* empty */
        });
};

handleLoad();
</script>

<style lang="scss">
.sm-workshop-view {
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
            .sm-workshop-registration-soon {
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
            .sm-workshop-price {
                padding: 0 1rem;
            }
        }
    }
}

@media screen and (max-width: 768px) {
    .sm-workshop-view .sm-workshop-page {
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
        }
    }
}
</style>
