<template>
    <router-link :to="to" class="sm-panel">
        <div v-if="image" class="sm-panel-image" :style="styleObject">
            <div v-if="dateInImage && date" class="sm-panel-image-date">
                <div class="sm-panel-image-date-day">
                    {{ computedDay }}
                </div>
                <div class="sm-panel-image-date-month">
                    {{ computedMonth }}
                </div>
            </div>
            <ion-icon
                v-if="imageUrl.length == 0"
                class="sm-panel-image-loader"
                name="image-outline" />
        </div>
        <div class="sm-panel-body">
            <h3 class="sm-panel-title">{{ title }}</h3>
            <div v-if="showDate && date" class="sm-panel-date">
                <ion-icon
                    v-if="showTime == false && endDate.length == 0"
                    name="calendar-outline" />
                <ion-icon v-else name="time-outline" />
                <p>{{ computedDate }}</p>
            </div>
            <div v-if="location" class="sm-panel-location">
                <ion-icon name="location-outline" />
                <p>{{ location }}</p>
            </div>
            <div v-if="content" class="sm-panel-content">
                {{ computedContent }}
            </div>
            <div v-if="button.length > 0" class="sm-panel-button">
                <SMButton
                    :to="to"
                    :type="buttonType"
                    :block="true"
                    :label="button" />
            </div>
            <div
                v-if="banner"
                :class="['sm-panel-banner', `sm-panel-banner-${bannerType}`]">
                {{ banner }}
            </div>
        </div>
    </router-link>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { api } from "../helpers/api";
import { MediaResponse } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { imageLoad } from "../helpers/image";
import { excerpt, replaceHtmlEntites, stripHtmlTags } from "../helpers/string";
import { isUUID } from "../helpers/uuid";
import SMButton from "./SMButton.vue";

const props = defineProps({
    title: {
        type: String,
        default: "",
        required: true,
    },
    image: {
        type: String,
        default: "",
        required: false,
    },
    icon: {
        type: String,
        default: "",
        required: false,
    },
    to: {
        type: Object,
        default: () => {
            return {};
        },
        required: true,
    },
    content: {
        type: String,
        default: "",
        required: false,
    },
    date: {
        type: String,
        default: "",
        required: false,
    },
    endDate: {
        type: String,
        default: "",
        required: false,
    },
    dateInImage: {
        type: Boolean,
        default: true,
        required: false,
    },
    showTime: {
        type: Boolean,
        default: false,
        required: false,
    },
    showDate: {
        type: Boolean,
        default: true,
        required: false,
    },
    location: {
        type: String,
        default: "",
        required: false,
    },
    button: {
        type: String,
        default: "",
        required: false,
    },
    buttonType: {
        type: String,
        default: "primary",
        required: false,
    },
    banner: {
        type: String,
        default: "",
        required: false,
    },
    bannerType: {
        type: String,
        default: "primary",
        required: false,
    },
});

let styleObject = reactive({});
let imageUrl = ref("");

/**
 * Return a human readable date based on props.date and props.endDate.
 */
const computedDate = computed(() => {
    let str = "";

    if (props.date.length > 0) {
        if (
            (props.endDate.length > 0 &&
                props.date.substring(0, props.date.indexOf(" ")) !=
                    props.endDate.substring(0, props.endDate.indexOf(" "))) ||
            props.showTime == false
        ) {
            str = new SMDate(props.date, { format: "yMd" }).format(
                "dd/MM/yyyy"
            );
            if (props.endDate.length > 0) {
                str =
                    str +
                    " - " +
                    new SMDate(props.endDate, { format: "yMd" }).format(
                        "dd/MM/yyyy"
                    );
            }
        } else {
            str = new SMDate(props.date, { format: "yMd" }).format(
                "dd/MM/yyyy @ h:mm aa"
            );
        }
    }

    return str;
});

/**
 * Return the content string cleaned from HTML.
 */
const computedContent = computed(() => {
    return excerpt(replaceHtmlEntites(stripHtmlTags(props.content)), 200);
});

const computedDay = computed(() => {
    return new SMDate(props.date, { format: "yMd" }).format("dd");
});

const computedMonth = computed(() => {
    return new SMDate(props.date, { format: "yMd" }).format("MMM");
});

onMounted(async () => {
    if (props.image && props.image.length > 0 && isUUID(props.image)) {
        api.get({ url: "/media/{medium}", params: { medium: props.image } })
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
    }
});

watch(
    () => imageUrl.value,
    (value) => {
        styleObject["backgroundImage"] = `url('${value}')`;
    }
);
</script>

<style lang="scss">
.sm-panel {
    display: flex;
    flex-direction: column;
    border: 1px solid $border-color;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 0 28px rgba(0, 0, 0, 0.05);
    max-width: 21rem;
    width: 100%;
    color: $font-color !important;
    margin-bottom: map-get($spacer, 5);
    transition: box-shadow 0.2s ease-in-out;
    position: relative;
    overflow: hidden;

    &:hover {
        color: $font-color;
        text-decoration: none;
        box-shadow: 0 0 14px rgba(0, 0, 0, 0.25);
    }

    .sm-panel-image {
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        height: map-get($spacer, 5) * 4;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        background-color: #eee;

        .sm-panel-image-loader {
            font-size: 5rem;
            color: $secondary-color;
        }

        .sm-panel-image-date {
            background-color: #fff;
            padding: 0.75rem 1rem;
            text-align: center;
            position: absolute;
            top: 15px;
            left: 15px;
            border-radius: 4px;
            box-shadow: 4px 4px 15px rgba(0, 0, 0, 0.2);
            text-align: center;

            .sm-panel-image-date-day {
                font-weight: bold;
                font-size: 130%;
            }

            .sm-panel-image-date-month {
                text-transform: uppercase;
                font-size: 80%;
            }
        }
    }

    .sm-panel-body {
        display: flex;
        flex-direction: column;
        flex: 1;
        padding: 0 map-get($spacer, 3) map-get($spacer, 3) map-get($spacer, 3);
        background-color: #fff;
    }

    .sm-panel-title {
        margin-bottom: 1rem;
    }

    .sm-panel-date,
    .sm-panel-location {
        display: flex;
        flex-direction: row;
        align-items: top;
        font-size: 80%;
        margin-bottom: 0.4rem;

        ion-icon {
            flex: 0 1 1rem;
            margin-right: map-get($spacer, 1);
            padding-top: 0.1rem;
            height: 1rem;
            padding: 0.25rem 0;
        }

        p {
            flex: 1;
            margin: 0;
            line-height: 1.5rem;
        }
    }

    .sm-panel-content {
        margin-top: 1rem;
        line-height: 130%;
        flex: 1;
    }

    .sm-panel-button {
        margin-top: map-get($spacer, 4);
    }

    .sm-panel-banner {
        position: absolute;
        display: flex;
        justify-content: center;
        align-items: center;
        top: 65px;
        right: -10px;
        height: 20px;
        width: 120px;
        font-size: 70%;
        text-transform: uppercase;
        font-weight: 800;
        color: #fff;
        background-color: $primary-color;
        transform-origin: 100%;
        transform: rotateZ(45deg);

        &.sm-panel-banner-success {
            background-color: $success-color;
        }

        &.sm-panel-banner-danger {
            background-color: $danger-color;
            font-size: 60%;
        }

        &.sm-panel-banner-warning {
            background-color: $warning-color-darker;
            color: $font-color;
            font-size: 60%;
        }

        &.sm-panel-banner-expired {
            background-color: purple;
        }
    }
}
</style>
