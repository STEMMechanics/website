<template>
    <div class="input-attachments">
        <label>Files</label>
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
        <SMButton
            type="seconday"
            :small="true"
            label="Add media"
            @click="handleClickAdd" />
    </div>
</template>

<script setup lang="ts">
import { ref, Ref, watch } from "vue";
import { openDialog } from "../components/SMDialog";
import { api } from "../helpers/api";
import { Media, MediaResponse } from "../helpers/api.types";
import { bytesReadable } from "../helpers/types";
import { getFilePreview } from "../helpers/utils";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";
import SMButton from "../components/SMButton.vue";

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
    openDialog(SMDialogMedia, { mime: "", accepts: "" })
        .then((result) => {
            const media = result as Media;

            mediaItems.value.push(media);
            value.value.push(media.id);

            emits("update:modelValue", value.value);
        })
        .catch(() => {
            /* empty */
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

    emits("update:modelValue", value.value);
};

/**
 * Load the attachment list
 */
const handleLoad = () => {
    mediaItems.value = [];

    if (value.value && typeof value.value.forEach === "function") {
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
    }
};

watch(
    () => props.modelValue,
    (newValue) => {
        value.value = newValue;
        handleLoad();
    }
);

handleLoad();
</script>

<style lang="scss">
.input-attachments {
    display: block;

    label {
        position: relative;
        display: block;
        padding: 8px 16px 0 16px;
        color: var(--base-color);
    }

    a.button {
        display: inline-block;
    }

    ul {
        list-style-type: none;
        padding: 0;
        border: 1px solid var(--base-color-border);

        li {
            background-color: var(--base-color-light);
            display: flex;
            align-items: center;
            padding: 16px;

            &.attachments-none {
                justify-content: center;

                ion-icon {
                    font-size: 115%;
                }

                p {
                    margin: 0;
                    padding-left: #{map-get($spacing, 2)};
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
                padding-left: #{map-get($spacing, 2)};
                color: var(--base-color-dark);
            }

            .attachment-media-remove {
                font-size: 115%;
                padding-top: #{map-get($spacing, 1)};
                margin-left: #{map-get($spacing, 3)};
                color: var(--base-color-text);
                cursor: pointer;

                &:hover {
                    color: var(--danger-color);
                }
            }
        }
    }
}
</style>
