<template>
    <SMPage :full="true" :loading="imageUrl.length == 0" class="workshop-view">
        <div
            class="workshop-image"
            :style="{ backgroundImage: `url('${imageUrl}')` }"></div>
        <SMContainer>
            <SMMessage
                v-if="formMessage.message"
                :icon="formMessage.icon"
                :type="formMessage.type"
                :message="formMessage.message"
                class="mt-5" />
            <SMContainer class="workshop-page">
                <div class="workshop-body">
                    <h2 class="workshop-title">{{ event.title }}</h2>
                    <SMHTML :html="event.content" class="workshop-content" />
                </div>
                <div class="workshop-info">
                    <div
                        v-if="
                            event.status == 'closed' ||
                            (event.status == 'open' &&
                                new SMDate(event.end_at, {format: 'ymd'}).isBefore()
                        "
                        class="workshop-registration workshop-registration-closed">
                        Registration for this event has closed
                    </div>
                    <div
                        v-if="event.status == 'cancelled'"
                        class="workshop-registration workshop-registration-cancelled">
                        This event has been cancelled
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            new SMDate(event.end_at, {
                                format: 'ymd',
                            }).isAfter() &&
                            event.registration_type == 'none'
                        "
                        class="workshop-registration workshop-registration-none">
                        Registration not required for this event.<br />Arrive
                        early to avoid disappointment as seating maybe limited.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            new SMDate(event.end_at, {
                                format: 'ymd',
                            }).isAfter() &&
                            event.registration_type != 'none'
                        "
                        class="workshop-registration workshop-registration-url">
                        <SMButton
                            :href="registerUrl"
                            label="Register for Event"></SMButton>
                    </div>
                    <div class="workshop-date">
                        <h4><ion-icon name="calendar-outline" />Date / Time</h4>
                        <p
                            v-for="(line, index) in workshopDate"
                            :key="index"
                            class="workshop-date-string">
                            {{ line }}
                        </p>
                    </div>
                    <div class="workshop-location">
                        <h4><ion-icon name="location-outline" />Location</h4>
                        <p>
                            {{
                                event.location == "online"
                                    ? "Online event"
                                    : event.address
                            }}
                        </p>
                    </div>
                </div>
            </SMContainer>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { api } from "../helpers/api";
import { computed, ref, reactive } from "vue";
import { useRoute } from "vue-router";
import { useApplicationStore } from "../store/ApplicationStore";
import { SMDate } from "../helpers/datetime";
import SMButton from "../components/SMButton.vue";
import SMHTML from "../components/SMHTML.vue";
import SMMessage from "../components/SMMessage.vue";
import SMPage from "../components/SMPage.vue";
import { ApiEvent, ApiMedia } from "../helpers/api.types";
import { imageLoad } from "../helpers/image";

const applicationStore = useApplicationStore();
const event = ref({});
const imageUrl = ref("");
const route = useRoute();
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

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
                    SMDate(event.value.end_at, { format: "ymd" }),
                format("h:mm aa"),
            ];
        }
    }

    return str;
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

const handleLoad = async () => {
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    api.get(`/events/${route.params.id}`)
        .then((result) => {
            event.value =
                result.data &&
                (result.data as ApiEvent).event &&
                Object.keys((result.data as ApiEvent).event).length > 0
                    ? (result.data as ApiEvent).event
                    : {};

            if (event.value) {
                // event.value = result.data.event as ApiEventItem;

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
                formMessage.message =
                    "Could not load event information from the server.";
            }
        })
        .catch((error) => {
            formMessage.message =
                error.data?.message ||
                "Could not load event information from the server.";
        });

    // try {
    //     const result = await api.get(`/events/${route.params.id}`);
    //     event.value = result.data.event as ApiEventItem;

    //     event.value.start_at = timestampUtcToLocal(event.value.start_at);
    //     event.value.end_at = timestampUtcToLocal(event.value.end_at);

    //     applicationStore.setDynamicTitle(event.value.title);
    //     handleLoadImage();
    // } catch (error) {
    //     formMessage.message =
    //         error.data?.message ||
    //         "Could not load event information from the server.";
    // }
};

const handleLoadImage = async () => {
    try {
        console.log(event.value);
        const result = await api.get(`/media/${event.value.hero}`);
        const data = result.data as ApiMedia;

        if (data && data.medium) {
            imageLoad(data.medium.url, (url) => {
                imageUrl.value = url;
            });
        }
    } catch (error) {
        /* empty */
    }
};

handleLoad();
</script>

<style lang="scss">
.workshop-view {
    .workshop-image {
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

        .workshop-image-loader {
            font-size: 5rem;
            color: $secondary-color;
        }
    }

    .workshop-page {
        display: flex;
        flex-direction: row;

        .workshop-body,
        .workshop-info {
            line-height: 1.5rem;
        }

        .workshop-body {
            flex: 1;
            text-align: left;
        }

        .workshop-info {
            width: 18rem;
            margin-left: 2rem;

            h4 {
                margin-bottom: 0.25rem;
                display: flex;
                align-items: center;
                height: 1rem;

                ion-icon {
                    display: inline-block;
                    width: 1rem;
                    margin-right: 0.5rem;
                }
            }

            p {
                margin: 0;
                padding-left: 1.5rem;
                font-size: 90%;
            }

            .workshop-registration {
                margin-top: 1.5rem;
                line-height: 1.25rem;

                .button {
                    display: block;
                }
            }

            .workshop-registration-none {
                border: 1px solid #ffeeba;
                background-color: #fff3cd;
                color: #856404;
                text-align: center;
                font-size: 80%;
                padding: 0.5rem;
            }

            .workshop-registration-closed,
            .workshop-registration-cancelled {
                border: 1px solid #f5c2c7;
                background-color: #f8d7da;
                color: #842029;
                text-align: center;
                font-size: 80%;
                padding: 0.5rem;
            }

            .workshop-date,
            .workshop-location {
                padding: 0 1rem;
            }
        }
    }
}

@media screen and (max-width: 768px) {
    .workshop-view .workshop-page {
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
        }
    }
}
</style>
