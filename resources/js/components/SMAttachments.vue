<template>
    <SMContainer class="sm-attachments">
        <h3 v-if="props.attachments && props.attachments.length > 0">
            Attachments
        </h3>
        <div
            v-for="file of props.attachments"
            :key="file.id"
            class="sm-attachment-row">
            <div class="sm-attachment-file-icon">
                <img
                    :src="getFileIconImagePath(file.title || file.name)"
                    height="48"
                    width="48" />
            </div>
            <a
                :href="file.url"
                target="_blank"
                class="sm-attachment-file-name"
                >{{ file.title || file.name }}</a
            >
            <div class="sm-attachment-file-size">
                ({{ bytesReadable(file.size) }})
            </div>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import { bytesReadable } from "../helpers/types";
import { getFileIconImagePath } from "../helpers/utils";
import SMContainer from "./SMContainer.vue";

const props = defineProps({
    attachments: {
        type: Object,
        required: true,
    },
});
</script>

<style lang="scss">
.sm-attachments {
    h3 {
        margin-top: map-get($spacer, 4);
        margin-bottom: 0;
    }

    .sm-attachment-row {
        border-bottom: 1px solid $secondary-background-color;
        display: flex;
        align-items: center;
        padding: 0.5rem 0;

        &:last-child {
            border-bottom: 0;
        }

        .sm-attachment-file-icon {
            display: flex;
            width: 64px;
            justify-content: center;
        }

        .sm-attachment-file-size {
            font-size: 75%;
            padding-left: 0.75rem;
            color: $secondary-color-dark;
        }
    }
}
</style>
