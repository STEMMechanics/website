<template>
    <div class="sm-input-group sm-input-attachments">
        <label>Attachments</label>
        <ul>
            <li v-if="mediaItems.length == 0" class="attachments-none">
                <ion-icon name="sad-outline"></ion-icon>
                <p>No attachments</p>
            </li>
            <li v-for="media of mediaItems" :key="media.id">
                <div class="attachment-media-icon">
                    <img
                        :src="getFilePreview(media.url)"
                        height="48"
                        width="48" />
                </div>
                <div class="attachment-media-name">
                    {{ media.title || media.name }}
                </div>
                <div class="attachment-media-size">
                    ({{ bytesReadable(media.size) }})
                </div>
                <div class="attachment-media-remove">
                    <ion-icon
                        name="close-outline"
                        title="Remove attachment"
                        @click="handleClickRemove(media.id)" />
                </div>
            </li>
        </ul>
        <a class="button" @click.prevent="handleClickAdd">Add media</a>
    </div>
</template>

<script setup lang="ts">
import { ref, Ref, watch } from "vue";
import { openDialog } from "vue3-promise-dialog";
import { api } from "../helpers/api";
import { Media, MediaResponse } from "../helpers/api.types";
import { bytesReadable } from "../helpers/types";
import { getFilePreview } from "../helpers/utils";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";

const props = defineProps({
    modelValue: {
        type: Array<string>,
        default: () => [],
        required: true,
    },
    accept: {
        type: String,
        default: "",
    },
});

const emits = defineEmits(["update:modelValue"]);
const value: Ref<string[]> = ref(props.modelValue);
const mediaItems: Ref<Media[]> = ref([]);

/**
 * Handle the user adding a new media item.
 */
const handleClickAdd = async () => {
    openDialog(SMDialogMedia, { mime: "", accepts: "" }).then((result) => {
        const media = result as Media;

        mediaItems.value.push(media);
        value.value.push(media.id);

        emits("update:modelValue", value);
    });
};

/**
 * Handle removing a media item from the attachment array.
 *
 * @param {string} media_id The media id to remove.
 */
const handleClickRemove = (media_id: string) => {
    const index = value.value.indexOf(media_id);
    if (index !== -1) {
        value.value.splice(index, 1);
    }

    const mediaIndex = mediaItems.value.findIndex(
        (media) => media.id === media_id
    );
    if (mediaIndex !== -1) {
        mediaItems.value.splice(mediaIndex, 1);
    }

    emits("update:modelValue", value);
};

/**
 * Load the attachment list
 */
const handleLoad = () => {
    mediaItems.value = [];

    value.value.forEach((item) => {
        api.get({
            url: `/media/${item}`,
        })
            .then((result) => {
                if (result.data) {
                    const data = result.data as MediaResponse;

                    mediaItems.value.push(data.medium);
                }
            })
            .catch(() => {
                /* empty */
            });
    });
};

watch(
    () => props.modelValue,
    (newValue) => {
        value.value = newValue;
    }
);

handleLoad();
</script>

<style lang="scss">
.sm-input-group.sm-input-attachments {
    display: block;

    label {
        position: relative;
        display: block;
        padding: map-get($spacer, 2) map-get($spacer, 3) map-get($spacer, 0)
            map-get($spacer, 3);
        line-height: 1.5;
        color: $secondary-color-dark;
    }

    a.button {
        display: inline-block;
    }

    ul {
        list-style-type: none;
        padding: 0;
        border: 1px solid $border-color;

        li {
            background-color: #fff;
            display: flex;
            align-items: center;
            padding: map-get($spacer, 2);

            &.attachments-none {
                justify-content: center;

                ion-icon {
                    font-size: 1.5rem;
                }

                p {
                    margin: 0;
                    padding-left: 0.5rem;
                }
            }

            .attachment-media-icon {
                display: flex;
                width: 64px;
                justify-content: center;
            }

            .attachment-media-name {
                flex: 1;
            }

            .attachment-media-size {
                font-size: 75%;
                padding-left: 0.75rem;
                color: $secondary-color-dark;
            }

            .attachment-media-remove {
                font-size: 1.5rem;
                padding-top: 0.2rem;
                margin-left: 1rem;
                color: $font-color;
                cursor: pointer;

                &:hover {
                    color: $danger-color;
                }
            }
        }
    }
}
</style>
