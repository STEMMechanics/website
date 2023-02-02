<template>
    <SMContainer :full="true" class="workshop-view">
        <div
            class="workshop-image"
            :style="{ backgroundImage: `url('${imageUrl}')` }">
            <font-awesome-icon
                v-if="imageUrl.length == 0"
                class="workshop-image-loader"
                icon="fa-regular fa-image" />
        </div>
        <template #inner>
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
                                timestampBeforeNow(event.end_at))
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
                            timestampAfterNow(event.end_at) &&
                            event.registration_type == 'none'
                        "
                        class="workshop-registration workshop-registration-none">
                        Registration not required for this event.<br />Arrive
                        early to avoid disappointment as seating maybe limited.
                    </div>
                    <div
                        v-if="
                            event.status == 'open' &&
                            timestampAfterNow(event.end_at) &&
                            event.registration_type != 'none'
                        "
                        class="workshop-registration workshop-registration-url">
                        <SMButton
                            :href="registerUrl"
                            label="Register for Event"></SMButton>
                    </div>
                    <div class="workshop-date">
                        <h4>
                            <font-awesome-icon
                                icon="fa-regular fa-calendar" />Date / Time
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
                            <font-awesome-icon
                                icon="fa-solid fa-location-dot" />Location
                        </h4>
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
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, ref, reactive } from "vue";
import { useRoute } from "vue-router";
import { useApplicationStore } from "../store/ApplicationStore";
import { format } from "date-fns";
import SMButton from "../components/SMButton.vue";
import SMHTML from "../components/SMHTML.vue";
import SMMessage from "../components/SMMessage.vue";
import {
    timestampUtcToLocal,
    timestampBeforeNow,
    timestampAfterNow,
} from "../helpers/common";

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
    let str = [];

    if (Object.keys(event.value).length > 0) {
        if (
            event.value.end_at.length > 0 &&
            event.value.start_at.substring(
                0,
                event.value.start_at.indexOf(" ")
            ) !=
                event.value.end_at.substring(0, event.value.end_at.indexOf(" "))
        ) {
            str = format(new Date(props.date), "dd/MM/yyyy");
            if (event.value.end_at.length > 0) {
                str =
                    str +
                    " - " +
                    format(new Date(event.value.end_at), "dd/MM/yyyy");
            }
        } else {
            str = [
                format(new Date(event.value.start_at), "EEEE dd MMM yyyy"),
                format(new Date(event.value.start_at), "h:mm aa") +
                    " - " +
                    format(new Date(event.value.end_at), "h:mm aa"),
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

    try {
        const result = await axios.get(`events/${route.params.id}`);
        event.value = result.data.event;

        event.value.start_at = timestampUtcToLocal(event.value.start_at);
        event.value.end_at = timestampUtcToLocal(event.value.end_at);

        applicationStore.setDynamicTitle(event.value.title);
        handleLoadImage();
    } catch (error) {
        formMessage.message =
            error.response?.data?.message ||
            "Could not load event information from the server.";
    }
};

const handleLoadImage = async () => {
    try {
        const result = await axios.get(`media/${event.value.hero}`);
        if (result.data.medium) {
            imageUrl.value = result.data.medium.url;
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

                svg {
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
