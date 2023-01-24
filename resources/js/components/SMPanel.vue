<template>
    <router-link :to="to" class="panel">
        <div
            class="panel-image"
            :style="{ backgroundImage: `url('${imageUrl}')` }">
            <div v-if="dateInImage" class="panel-image-date">
                <div class="panel-image-date-day">
                    {{ format(new Date(date), "dd") }}
                </div>
                <div class="panel-image-date-month">
                    {{ format(new Date(date), "MMM") }}
                </div>
            </div>
            <font-awesome-icon
                v-if="hideImageLoader == false"
                class="panel-image-loader"
                icon="fa-regular fa-image" />
        </div>
        <div class="panel-body">
            <h3 class="panel-title">{{ title }}</h3>
            <div class="panel-date">
                <font-awesome-icon
                    v-if="showTime == false && endDate.length == 0"
                    icon="fa-regular fa-calendar" /><font-awesome-icon
                    v-else
                    icon="fa-regular fa-clock" />
                <p>{{ panelDate }}</p>
            </div>
            <div v-if="location" class="panel-location">
                <font-awesome-icon icon="fa-solid fa-location-dot" />
                <p>{{ location }}</p>
            </div>
            <div v-if="content" class="panel-content">{{ panelContent }}</div>
        </div>
    </router-link>
</template>

<script setup lang="ts">
import axios from "axios";
import { onMounted, computed, ref } from "vue";
import {
    excerpt,
    isUUID,
    replaceHtmlEntites,
    stripHtmlTags,
} from "../helpers/common";
import { format, parseISO } from "date-fns";

const props = defineProps({
    title: {
        type: String,
        default: "",
        required: true,
    },
    image: {
        type: String,
        default: "",
        required: true,
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
        required: true,
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
    location: {
        type: String,
        default: "",
        required: false,
    },
});

let imageUrl = ref(props.image);
const panelDate = computed(() => {
    let str = "";

    if (
        (props.endDate.length > 0 &&
            props.date.substring(0, props.date.indexOf(" ")) !=
                props.endDate.substring(0, props.endDate.indexOf(" "))) ||
        props.showTime == false
    ) {
        str = format(new Date(props.date), "dd/MM/yyyy");
        if (props.endDate.length > 0) {
            str = str + " - " + format(new Date(props.endDate), "dd/MM/yyyy");
        }
    } else {
        str = format(new Date(props.date), "dd/MM/yyyy @ h:mm aa");
    }

    return str;
});

const panelContent = computed(() => {
    return excerpt(replaceHtmlEntites(stripHtmlTags(props.content)), 250);
});

const hideImageLoader = computed(() => {
    return (
        imageUrl.value &&
        imageUrl.value.length > 0 &&
        isUUID(imageUrl.value) == false
    );
});

onMounted(async () => {
    if (imageUrl.value && imageUrl.value.length > 0 && isUUID(imageUrl.value)) {
        try {
            let result = await axios.get(`media/${props.image}`);

            if (result.data.medium) {
                imageUrl.value = result.data.medium.url;
            }
        } catch (error) {
            /* empty */
        }
    }
});
</script>

<style lang="scss">
.panel {
    display: flex;
    flex-direction: column;
    border: 1px solid $border-color;
    border-radius: 12px;
    box-shadow: 0 0 28px rgba(0, 0, 0, 0.05);
    max-width: 18rem;
    width: 100%;
    color: $font-color !important;
    margin-bottom: map-get($spacer, 5);
    transition: box-shadow 0.2s ease-in-out;

    &:hover {
        color: $font-color;
        text-decoration: none;
        box-shadow: 0 0 14px rgba(0, 0, 0, 0.25);
    }

    .panel-image {
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

        .panel-image-loader {
            font-size: 5rem;
            color: $secondary-color;
        }

        .panel-image-date {
            background-color: #fff;
            padding: 0.75rem 1rem;
            text-align: center;
            position: absolute;
            top: 15px;
            left: 15px;
            border-radius: 4px;
            box-shadow: 4px 4px 15px rgba(0, 0, 0, 0.2);
            text-align: center;

            .panel-image-date-day {
                font-weight: bold;
                font-size: 130%;
            }

            .panel-image-date-month {
                text-transform: uppercase;
                font-size: 80%;
            }
        }
    }

    .panel-body {
        padding: 0 map-get($spacer, 3) map-get($spacer, 3) map-get($spacer, 3);
    }

    .panel-title {
        margin-bottom: 1rem;
    }

    .panel-date,
    .panel-location {
        display: flex;
        flex-direction: row;
        align-items: top;
        font-size: 80%;
        margin-bottom: 0.4rem;

        svg {
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

    .panel-content {
        margin-top: 1rem;
    }
}
</style>
